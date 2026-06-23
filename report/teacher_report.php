<?php
// teacher_report.php - Teacher Report Generator
// Shows teacher information, assigned subjects, and performance analysis

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
    if ($role_id == 1 || $role_id == 2 || $role_id == 3 || $role_id == 5 || $role_id == 15) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission && !$is_super_admin) {
    $_SESSION['error'] = "You don't have permission to view teacher reports.";
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
$action = $_GET['action'] ?? 'list'; // list, view, export_pdf, export_excel
$teacher_id = $_GET['id'] ?? 0;
$filter_role = $_GET['role'] ?? '';
$filter_gender = $_GET['gender'] ?? '';
$filter_status = $_GET['status'] ?? 'Active';
$search_term = $_GET['search'] ?? '';

// Get all roles for dropdown
$roles_sql = "SELECT id, role_name FROM admin_roles ORDER BY role_name";
$roles_result = mysqli_query($conn, $roles_sql);
$all_roles = [];
while ($row = mysqli_fetch_assoc($roles_result)) {
    $all_roles[] = $row;
}

// ==================== FUNCTIONS ====================

function getTeacherSubjects($teacher_id, $conn) {
    $sql = "SELECT 
                sta.id as assignment_id,
                sta.subject,
                sta.form_level,
                sta.academic_year,
                sta.is_primary,
                sta.can_enter_results,
                sta.assigned_at,
                CONCAT(a.first_name, ' ', a.last_name) as assigned_by_name
            FROM subject_teacher_assignments sta
            LEFT JOIN admins a ON sta.assigned_by = a.id
            WHERE sta.teacher_id = ?
            ORDER BY sta.form_level, sta.subject";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    $stmt->close();
    return $subjects;
}

function getTeacherClassAssignments($teacher_id, $conn) {
    $sql = "SELECT 
                tca.id,
                tca.class_level,
                tca.combination,
                tca.assigned_at,
                CONCAT(a.first_name, ' ', a.last_name) as assigned_by_name
            FROM teacher_class_assignments tca
            LEFT JOIN admins a ON tca.assigned_by = a.id
            WHERE tca.teacher_id = ?
            ORDER BY tca.class_level, tca.combination";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $classes = [];
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    $stmt->close();
    return $classes;
}

function getTeacherSubjectPerformance($teacher_id, $subject, $form_level, $conn) {
    $performance = [
        'students_count' => 0,
        'average_score' => 0,
        'highest_score' => 0,
        'lowest_score' => 100,
        'pass_count' => 0,
        'fail_count' => 0,
        'division_counts' => [],
        'subject_marks' => []
    ];
    
    // Determine which table to query
    $table = ($form_level == 'Form Five') ? 'form_five_results' : 'form_six_results';
    
    // Get latest active exam for this form level
    $exam_sql = "SELECT id, exam_name FROM exam_types WHERE form_level = ? AND is_active = 1 ORDER BY year DESC, id DESC LIMIT 1";
    $exam_stmt = $conn->prepare($exam_sql);
    $exam_stmt->bind_param("s", $form_level);
    $exam_stmt->execute();
    $exam_result = $exam_stmt->get_result();
    $exam = $exam_result->fetch_assoc();
    $exam_stmt->close();
    
    if (!$exam) {
        return $performance;
    }
    
    // Get results for this subject
    $sql = "SELECT 
                fr.$subject as marks,
                fr.division,
                s.first_name,
                s.last_name,
                s.index_number
            FROM $table fr
            JOIN students s ON fr.student_id = s.id
            WHERE fr.exam_type_id = ?
            AND fr.$subject IS NOT NULL
            AND fr.$subject != ''";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $exam['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $total = 0;
    $count = 0;
    $divisions = [];
    
    while ($row = $result->fetch_assoc()) {
        $marks = (int)$row['marks'];
        $total += $marks;
        $count++;
        
        if ($marks > $performance['highest_score']) {
            $performance['highest_score'] = $marks;
        }
        if ($marks < $performance['lowest_score']) {
            $performance['lowest_score'] = $marks;
        }
        
        if ($marks >= 50) {
            $performance['pass_count']++;
        } else {
            $performance['fail_count']++;
        }
        
        $division = $row['division'] ?? 'N/A';
        if (!isset($divisions[$division])) {
            $divisions[$division] = 0;
        }
        $divisions[$division]++;
        
        $performance['subject_marks'][] = [
            'marks' => $marks,
            'division' => $division,
            'student_name' => $row['first_name'] . ' ' . $row['last_name'],
            'index_number' => $row['index_number']
        ];
    }
    $stmt->close();
    
    $performance['students_count'] = $count;
    $performance['average_score'] = $count > 0 ? round($total / $count, 2) : 0;
    $performance['division_counts'] = $divisions;
    $performance['exam_name'] = $exam['exam_name'] ?? 'N/A';
    
    return $performance;
}

function getTeacherOverallPerformance($teacher_id, $conn) {
    $subjects = getTeacherSubjects($teacher_id, $conn);
    $overall = [];
    $total_students = 0;
    $total_average = 0;
    $subject_count = 0;
    
    foreach ($subjects as $subject) {
        $performance = getTeacherSubjectPerformance(
            $teacher_id, 
            $subject['subject'], 
            $subject['form_level'], 
            $conn
        );
        
        if ($performance['students_count'] > 0) {
            $overall[] = [
                'subject' => $subject['subject'],
                'form_level' => $subject['form_level'],
                'students_count' => $performance['students_count'],
                'average_score' => $performance['average_score'],
                'highest_score' => $performance['highest_score'],
                'lowest_score' => $performance['lowest_score'],
                'pass_count' => $performance['pass_count'],
                'fail_count' => $performance['fail_count'],
                'exam_name' => $performance['exam_name']
            ];
            $total_students += $performance['students_count'];
            $total_average += $performance['average_score'];
            $subject_count++;
        }
    }
    
    $overall_summary = [
        'total_subjects' => count($overall),
        'total_students' => $total_students,
        'overall_average' => $subject_count > 0 ? round($total_average / $subject_count, 2) : 0,
        'subject_performance' => $overall
    ];
    
    return $overall_summary;
}

// ==================== GET TEACHER DATA ====================
if ($action == 'view' && $teacher_id > 0) {
    $teacher_sql = "SELECT 
                        a.*,
                        GROUP_CONCAT(DISTINCT ar.role_name ORDER BY ara.is_primary DESC, ar.role_name SEPARATOR ', ') as roles,
                        GROUP_CONCAT(DISTINCT CASE WHEN ara.is_primary = 1 THEN ar.role_name END) as primary_role,
                        (SELECT COUNT(*) FROM subject_teacher_assignments WHERE teacher_id = a.id) as total_subjects,
                        (SELECT COUNT(*) FROM teacher_class_assignments WHERE teacher_id = a.id) as total_classes
                    FROM admins a
                    LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
                    LEFT JOIN admin_roles ar ON ara.role_id = ar.id
                    WHERE a.id = ? AND a.school_id = ?
                    GROUP BY a.id";
    $teacher_stmt = $conn->prepare($teacher_sql);
    $teacher_stmt->bind_param("ii", $teacher_id, $school_id);
    $teacher_stmt->execute();
    $teacher_result = $teacher_stmt->get_result();
    $teacher = $teacher_result->fetch_assoc();
    $teacher_stmt->close();
    
    if (!$teacher) {
        header("Location: teacher_report.php?error=Teacher not found");
        exit();
    }
    
    // Get teacher subjects
    $teacher_subjects = getTeacherSubjects($teacher_id, $conn);
    
    // Get teacher class assignments
    $teacher_classes = getTeacherClassAssignments($teacher_id, $conn);
    
    // Get overall performance
    $performance_summary = getTeacherOverallPerformance($teacher_id, $conn);
    
    // Get detailed performance per subject
    $subject_performance = [];
    foreach ($teacher_subjects as $subject) {
        $perf = getTeacherSubjectPerformance(
            $teacher_id, 
            $subject['subject'], 
            $subject['form_level'], 
            $conn
        );
        if ($perf['students_count'] > 0) {
            $subject_performance[] = [
                'subject' => $subject['subject'],
                'form_level' => $subject['form_level'],
                'performance' => $perf
            ];
        }
    }
}

// ==================== GET TEACHER LIST ====================
if ($action == 'list' || $action == 'export_pdf' || $action == 'export_excel') {
    $where_conditions = ["a.school_id = ?"];
    $params = [$school_id];
    $param_types = "i";
    
    if (!empty($filter_role)) {
        $where_conditions[] = "EXISTS (SELECT 1 FROM admin_role_assignments ara2 JOIN admin_roles ar2 ON ara2.role_id = ar2.id WHERE ara2.admin_id = a.id AND ar2.role_name = ?)";
        $params[] = $filter_role;
        $param_types .= "s";
    }
    
    if (!empty($filter_gender)) {
        $where_conditions[] = "a.sex = ?";
        $params[] = $filter_gender;
        $param_types .= "s";
    }
    
    if ($filter_status == 'Active') {
        $where_conditions[] = "a.status = 1";
    } elseif ($filter_status == 'Inactive') {
        $where_conditions[] = "a.status = 0";
    }
    
    if (!empty($search_term)) {
        $where_conditions[] = "(a.first_name LIKE ? OR a.last_name LIKE ? OR a.email LIKE ?)";
        $search = "%$search_term%";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $param_types .= "sss";
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    $sql = "SELECT 
                a.id,
                a.first_name,
                a.middle_name,
                a.last_name,
                a.sex,
                a.email,
                a.phone_number,
                a.nida,
                a.status,
                a.profile_image,
                a.created_at,
                GROUP_CONCAT(DISTINCT ar.role_name ORDER BY ara.is_primary DESC, ar.role_name SEPARATOR ', ') as roles,
                GROUP_CONCAT(DISTINCT CASE WHEN ara.is_primary = 1 THEN ar.role_name END) as primary_role,
                (SELECT COUNT(*) FROM subject_teacher_assignments WHERE teacher_id = a.id) as total_subjects,
                (SELECT COUNT(*) FROM teacher_class_assignments WHERE teacher_id = a.id) as total_classes
            FROM admins a
            LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
            LEFT JOIN admin_roles ar ON ara.role_id = ar.id
            $where_clause
            GROUP BY a.id
            ORDER BY a.first_name, a.last_name";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $teacher_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    $total_teachers = count($teacher_list);
}

// ==================== EXPORT PDF ====================
if ($action == 'export_pdf') {
    require_once('../tcpdf/tcpdf.php');
    
    class TeacherPDF extends TCPDF {
        public $school_name = '';
        public $school_motto = '';
        public $logo_path = '';
        
        public function Header() {
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
                        $this->Image($full_logo_path, 15, 10, 25, 25, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                        $logo_used = true;
                    }
                }
            }
            
            $this->SetY(12);
            $this->SetFont('helvetica', 'B', 14);
            $this->Cell(0, 0, strtoupper($this->school_name), 0, 1, 'C');
            $this->SetFont('helvetica', 'I', 10);
            $this->Cell(0, 0, $this->school_motto, 0, 1, 'C');
            $this->SetY(35);
            $this->Line(15, $this->GetY(), 195, $this->GetY());
            $this->SetY($this->GetY() + 8);
        }
        
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages() . ' | Generated: ' . date('d/m/Y H:i:s'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }
    
    $pdf = new TeacherPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->school_name = $school_name;
    $pdf->school_motto = $school_motto;
    $pdf->logo_path = $school_logo_path;
    
    $pdf->SetCreator($school_name);
    $pdf->SetAuthor('Administrator');
    $pdf->SetTitle('Teachers Report - ' . $school_name);
    $pdf->SetMargins(15, 45, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();
    
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'TEACHERS REPORT', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Generated: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    $pdf->Ln(5);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(59, 157, 179);
    $pdf->SetTextColor(255);
    $pdf->SetDrawColor(59, 157, 179);
    $pdf->SetLineWidth(0.3);
    
    $headers = ['S/N', 'ID', 'Full Name', 'Gender', 'Email', 'Phone', 'Roles', 'Subjects', 'Status'];
    $col_widths = [10, 15, 45, 18, 40, 25, 30, 15, 15];
    
    for($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($col_widths[$i], 8, $headers[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', '', 8);
    $fill = false;
    $sn = 1;
    
    foreach($teacher_list as $teacher) {
        if($fill) {
            $pdf->SetFillColor(240, 248, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $full_name = $teacher['first_name'];
        if (!empty($teacher['middle_name'])) {
            $full_name .= ' ' . $teacher['middle_name'];
        }
        $full_name .= ' ' . $teacher['last_name'];
        
        $pdf->Cell($col_widths[0], 8, $sn, 1, 0, 'C', $fill);
        $pdf->Cell($col_widths[1], 8, $teacher['id'], 1, 0, 'C', $fill);
        $pdf->Cell($col_widths[2], 8, $full_name, 1, 0, 'L', $fill);
        $pdf->Cell($col_widths[3], 8, $teacher['sex'], 1, 0, 'C', $fill);
        $pdf->Cell($col_widths[4], 8, $teacher['email'] ?: 'N/A', 1, 0, 'L', $fill);
        $pdf->Cell($col_widths[5], 8, $teacher['phone_number'] ?: 'N/A', 1, 0, 'C', $fill);
        $pdf->Cell($col_widths[6], 8, $teacher['roles'] ?: 'N/A', 1, 0, 'L', $fill);
        $pdf->Cell($col_widths[7], 8, $teacher['total_subjects'] ?? 0, 1, 0, 'C', $fill);
        $pdf->Cell($col_widths[8], 8, $teacher['status'] ? 'Active' : 'Inactive', 1, 0, 'C', $fill);
        $pdf->Ln();
        $fill = !$fill;
        $sn++;
    }
    
    if (isset($temp_logo_path) && file_exists($temp_logo_path)) {
        @unlink($temp_logo_path);
    }
    
    $pdf->Output('teachers_report_' . date('Y-m-d') . '.pdf', 'D');
    exit();
}

// ==================== EXPORT EXCEL ====================
if ($action == 'export_excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="teachers_report_' . date('Y-m-d') . '.xls"');
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
        </style>
    </head>
    <body>';
    
    echo '<div class="header">' . strtoupper(htmlspecialchars($school_name)) . '</div>';
    echo '<div style="text-align:center; margin-bottom:20px;">TEACHERS REPORT - Generated: ' . date('d/m/Y H:i:s') . '</div>';
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>S/N</th>';
    echo '<th>ID</th>';
    echo '<th>Full Name</th>';
    echo '<th>Gender</th>';
    echo '<th>Email</th>';
    echo '<th>Phone</th>';
    echo '<th>Roles</th>';
    echo '<th>Primary Role</th>';
    echo '<th>Subjects</th>';
    echo '<th>Classes</th>';
    echo '<th>Status</th>';
    echo '</tr>';
    
    $sn = 1;
    foreach($teacher_list as $teacher) {
        $full_name = $teacher['first_name'];
        if (!empty($teacher['middle_name'])) {
            $full_name .= ' ' . $teacher['middle_name'];
        }
        $full_name .= ' ' . $teacher['last_name'];
        
        echo '<tr>';
        echo '<td>' . $sn . '</td>';
        echo '<td>' . $teacher['id'] . '</td>';
        echo '<td>' . htmlspecialchars($full_name) . '</td>';
        echo '<td>' . htmlspecialchars($teacher['sex']) . '</td>';
        echo '<td>' . htmlspecialchars($teacher['email'] ?: 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($teacher['phone_number'] ?: 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($teacher['roles'] ?: 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($teacher['primary_role'] ?: 'N/A') . '</td>';
        echo '<td>' . ($teacher['total_subjects'] ?? 0) . '</td>';
        echo '<td>' . ($teacher['total_classes'] ?? 0) . '</td>';
        echo '<td>' . ($teacher['status'] ? 'Active' : 'Inactive') . '</td>';
        echo '</tr>';
        $sn++;
    }
    
    echo '</table>';
    echo '</body></html>';
    exit();
}

include '../controller/header.php';
include '../controller/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">
                <i class="fas fa-chalkboard-teacher me-2"></i>Teacher Report
                <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($school_name); ?></span>
            </h2>
            <div>
                <a href="admins.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Staff
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
                        <span class="badge bg-info fs-6">Total Teachers: <?php echo isset($teacher_list) ? count($teacher_list) : 0; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_super_admin && count($all_schools) > 1): ?>
        <!-- School Selector -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h5 class="mb-0"><i class="fas fa-building me-2"></i>Select School</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="teacher_report.php" class="row g-3">
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
                        <a href="teacher_report.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($action == 'view' && isset($teacher)): ?>
        <!-- Individual Teacher Report -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-user-tie me-2"></i>
                        Teacher Report: <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                        <span class="badge bg-light text-dark ms-2">ID: <?php echo $teacher['id']; ?></span>
                    </h4>
                    <div>
                        <a href="teacher_report.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Back to List
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Teacher Info -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <table class="table table-bordered table-sm">
                            <tr>
                                <th width="35%">Full Name</th>
                                <td>
                                    <strong><?php echo htmlspecialchars($teacher['first_name'] . ' ' . ($teacher['middle_name'] ?? '') . ' ' . $teacher['last_name']); ?></strong>
                                </td>
                            </tr>
                            <tr>
                                <th>Gender</th>
                                <td>
                                    <span class="badge <?php echo $teacher['sex'] == 'Male' ? 'bg-info' : 'bg-pink'; ?>">
                                        <?php echo htmlspecialchars($teacher['sex']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><?php echo htmlspecialchars($teacher['email'] ?: 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Phone Number</th>
                                <td><?php echo htmlspecialchars($teacher['phone_number'] ?: 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>NIDA</th>
                                <td><?php echo htmlspecialchars($teacher['nida'] ?: 'N/A'); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered table-sm">
                            <tr>
                                <th width="35%">Primary Role</th>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($teacher['primary_role'] ?: 'N/A'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>All Roles</th>
                                <td><?php echo htmlspecialchars($teacher['roles'] ?: 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Total Subjects</th>
                                <td><span class="badge bg-success"><?php echo $teacher['total_subjects'] ?? 0; ?></span></td>
                            </tr>
                            <tr>
                                <th>Total Classes</th>
                                <td><span class="badge bg-info"><?php echo $teacher['total_classes'] ?? 0; ?></span></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <span class="badge <?php echo $teacher['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $teacher['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Subject Assignments -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h6 class="mb-2"><i class="fas fa-book me-2"></i>Subject Assignments</h6>
                        <?php if (!empty($teacher_subjects)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Subject</th>
                                        <th>Form Level</th>
                                        <th>Academic Year</th>
                                        <th>Primary</th>
                                        <th>Can Enter Results</th>
                                        <th>Assigned By</th>
                                        <th>Assigned Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teacher_subjects as $subject): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?php echo strtoupper($subject['subject']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($subject['form_level']); ?></td>
                                        <td><?php echo htmlspecialchars($subject['academic_year']); ?></td>
                                        <td>
                                            <?php if ($subject['is_primary']): ?>
                                                <span class="badge bg-warning text-dark"><i class="fas fa-star"></i> Primary</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Secondary</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($subject['can_enter_results']): ?>
                                                <span class="badge bg-success">Yes</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($subject['assigned_by_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($subject['assigned_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">No subject assignments found for this teacher.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Class Assignments -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h6 class="mb-2"><i class="fas fa-users me-2"></i>Class Assignments</h6>
                        <?php if (!empty($teacher_classes)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Class Level</th>
                                        <th>Combination</th>
                                        <th>Assigned By</th>
                                        <th>Assigned Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teacher_classes as $class): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($class['class_level']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($class['combination']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($class['assigned_by_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($class['assigned_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">No class assignments found for this teacher.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Performance Summary -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h6 class="mb-2"><i class="fas fa-chart-line me-2"></i>Performance Summary</h6>
                        <?php if (!empty($performance_summary['subject_performance'])): ?>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Subjects</h5>
                                        <h2 class="text-primary"><?php echo $performance_summary['total_subjects']; ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Students</h5>
                                        <h2 class="text-success"><?php echo $performance_summary['total_students']; ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="card-title">Overall Average</h5>
                                        <h2 class="text-info"><?php echo $performance_summary['overall_average']; ?>%</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="card-title">Status</h5>
                                        <h4>
                                            <span class="badge <?php echo $performance_summary['overall_average'] >= 50 ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $performance_summary['overall_average'] >= 50 ? 'Good' : 'Needs Improvement'; ?>
                                            </span>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Subject Performance Details -->
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Subject</th>
                                        <th>Form Level</th>
                                        <th>Students</th>
                                        <th>Average</th>
                                        <th>Highest</th>
                                        <th>Lowest</th>
                                        <th>Passed</th>
                                        <th>Failed</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($performance_summary['subject_performance'] as $perf): 
                                        $status_class = $perf['average_score'] >= 50 ? 'bg-success' : 'bg-danger';
                                        $status_text = $perf['average_score'] >= 50 ? 'Good' : 'Needs Improvement';
                                    ?>
                                    <tr>
                                        <td><strong><?php echo strtoupper($perf['subject']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($perf['form_level']); ?></td>
                                        <td><?php echo $perf['students_count']; ?></td>
                                        <td>
                                            <strong><?php echo $perf['average_score']; ?>%</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $perf['highest_score']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger"><?php echo $perf['lowest_score']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $perf['pass_count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger"><?php echo $perf['fail_count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">No performance data available. This teacher may not have any exam results yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Teacher List -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-list me-2"></i>Teacher List
                    </h4>
                    <div>
                        <span class="badge bg-light text-dark">
                            <?php echo isset($teacher_list) ? count($teacher_list) : 0; ?> teachers found
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" action="teacher_report.php" id="filterForm" class="mb-4">
                    <?php if ($is_super_admin && $school_id > 0): ?>
                        <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="">All Roles</option>
                                <?php foreach ($all_roles as $role): ?>
                                <option value="<?php echo htmlspecialchars($role['role_name']); ?>" <?php echo $filter_role == $role['role_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['role_name']); ?>
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
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Name, Email..." value="<?php echo htmlspecialchars($search_term); ?>">
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
                                    <a href="teacher_report.php<?php echo ($is_super_admin && $school_id > 0) ? '?school_id=' . $school_id : ''; ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-redo me-1"></i>Reset
                                    </a>
                                </div>
                                <div>
                                    <a href="teacher_report.php?action=export_pdf<?php echo ($is_super_admin && $school_id > 0) ? '&school_id=' . $school_id : ''; ?>&<?php echo http_build_query($_GET); ?>" 
                                       class="btn btn-danger btn-sm">
                                        <i class="fas fa-file-pdf me-1"></i>PDF Report
                                    </a>
                                    <a href="teacher_report.php?action=export_excel<?php echo ($is_super_admin && $school_id > 0) ? '&school_id=' . $school_id : ''; ?>&<?php echo http_build_query($_GET); ?>" 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-file-excel me-1"></i>Excel Report
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Teacher Table -->
                <?php if (!empty($teacher_list)): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="teacherTable">
                        <thead class="table-light">
                            <tr>
                                <th>S/N</th>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Gender</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Primary Role</th>
                                <th>Subjects</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teacher_list as $index => $teacher): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo $teacher['id']; ?></strong></td>
                                <td>
                                    <?php 
                                    $full_name = $teacher['first_name'];
                                    if (!empty($teacher['middle_name'])) {
                                        $full_name .= ' ' . $teacher['middle_name'];
                                    }
                                    $full_name .= ' ' . $teacher['last_name'];
                                    echo htmlspecialchars($full_name);
                                    ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $teacher['sex'] == 'Male' ? 'bg-info' : 'bg-pink'; ?>">
                                        <?php echo htmlspecialchars($teacher['sex']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($teacher['email'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($teacher['phone_number'] ?: 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($teacher['primary_role'] ?: 'N/A'); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $teacher['total_subjects'] ?? 0; ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $teacher['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $teacher['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="teacher_report.php?action=view&id=<?php echo $teacher['id']; ?><?php echo ($is_super_admin && $school_id > 0) ? '&school_id=' . $school_id : ''; ?>" 
                                       class="btn btn-sm btn-info" title="View Full Report">
                                        <i class="fas fa-file-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h4>No teachers found</h4>
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

.card-title {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 5px;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.85rem;
    }
    .btn-sm {
        padding: 0.2rem 0.4rem;
    }
    .card-title {
        font-size: 0.8rem;
    }
}
</style>

<?php include '../controller/footer.php'; ?>