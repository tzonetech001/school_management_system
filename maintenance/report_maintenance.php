<?php
// edit_admin.php
session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';

// Check if user has permission (Head Master or Second Master only)
$admin_id = $_SESSION['admin_id'] ?? 0;

// Get current user's roles
$user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}

// Check if user has Head Master (1) or Second Master (2) role
$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2 || $role_id == 3 || $role_id == 16) { // Head Master or Second Master
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view page you need.";
    header("Location: ../404.php");
    exit();
}
// Default filter values
$report_type = $_GET['report_type'] ?? 'student'; // 'student' or 'staff'
$filter_class = $_GET['class'] ?? '';
$filter_combination = $_GET['combination'] ?? '';
$filter_gender = $_GET['gender'] ?? '';
$filter_status = $_GET['status'] ?? 'active';
$assign_status = $_GET['assign_status'] ?? 'all'; // 'all', 'assigned', 'available'
$item_type = $_GET['item_type'] ?? 'all'; // 'all', 'table', 'chair'
$sort_order = $_GET['sort_order'] ?? 'index_asc'; // 'index_asc', 'index_desc', 'name_asc', 'name_desc'

// Column inclusion options
$include_combination = isset($_GET['include_combination']) && $_GET['include_combination'] == 1;
$include_gender = isset($_GET['include_gender']) && $_GET['include_gender'] == 1;
$include_class = isset($_GET['include_class']) && $_GET['include_class'] == 1;
$include_email = isset($_GET['include_email']) && $_GET['include_email'] == 1;
$include_role = isset($_GET['include_role']) && $_GET['include_role'] == 1;
$include_table = isset($_GET['include_table']) && $_GET['include_table'] == 1;
$include_chair = isset($_GET['include_chair']) && $_GET['include_chair'] == 1;
$include_item_details = isset($_GET['include_item_details']) && $_GET['include_item_details'] == 1;

// Get all combinations for filter dropdown
$combinations_sql = "SELECT DISTINCT combination FROM students WHERE combination IS NOT NULL AND combination != '' ORDER BY combination";
$combinations_result = mysqli_query($conn, $combinations_sql);
$combinations = [];
while ($row = mysqli_fetch_assoc($combinations_result)) {
    $combinations[] = $row['combination'];
}

// Get all classes for filter dropdown
$classes_sql = "SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class";
$classes_result = mysqli_query($conn, $classes_sql);
$classes = [];
while ($row = mysqli_fetch_assoc($classes_result)) {
    $classes[] = $row['class'];
}

// Build query based on report type
if ($report_type == 'student') {
    // STUDENT REPORT
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    // Build WHERE conditions for students
    if (!empty($filter_class)) {
        $where_conditions[] = "s.class = ?";
        $params[] = $filter_class;
        $param_types .= 's';
    }
    
    if (!empty($filter_combination)) {
        $where_conditions[] = "s.combination = ?";
        $params[] = $filter_combination;
        $param_types .= 's';
    }
    
    if (!empty($filter_gender)) {
        $where_conditions[] = "s.sex = ?";
        $params[] = $filter_gender;
        $param_types .= 's';
    }
    
    if ($filter_status == 'active') {
        $where_conditions[] = "s.status = 1";
    } elseif ($filter_status == 'inactive') {
        $where_conditions[] = "s.status = 0";
    }
    
    // Filter by assignment status
    if ($assign_status == 'assigned') {
        $where_conditions[] = "EXISTS (
            SELECT 1 FROM maintenance_assignments ma 
            WHERE ma.student_id = s.id AND ma.status = 'active'
        )";
    } elseif ($assign_status == 'available') {
        $where_conditions[] = "NOT EXISTS (
            SELECT 1 FROM maintenance_assignments ma 
            WHERE ma.student_id = s.id AND ma.status = 'active'
        )";
    }
    
    // Build base query
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
                s.is_leaver,
                
                -- Get assigned table
                (SELECT mi.item_code 
                 FROM maintenance_assignments ma 
                 JOIN maintenance_items mi ON ma.item_id = mi.id 
                 WHERE ma.student_id = s.id 
                 AND ma.status = 'active' 
                 AND ma.assignment_type = 'table'
                 LIMIT 1) as assigned_table,
                 
                -- Get assigned chair
                (SELECT mi.item_code 
                 FROM maintenance_assignments ma 
                 JOIN maintenance_items mi ON ma.item_id = mi.id 
                 WHERE ma.student_id = s.id 
                 AND ma.status = 'active' 
                 AND ma.assignment_type = 'chair'
                 LIMIT 1) as assigned_chair,
                 
                -- Get table assignment date
                (SELECT ma.assigned_date 
                 FROM maintenance_assignments ma 
                 JOIN maintenance_items mi ON ma.item_id = mi.id 
                 WHERE ma.student_id = s.id 
                 AND ma.status = 'active' 
                 AND ma.assignment_type = 'table'
                 LIMIT 1) as table_assigned_date,
                 
                -- Get chair assignment date
                (SELECT ma.assigned_date 
                 FROM maintenance_assignments ma 
                 JOIN maintenance_items mi ON ma.item_id = mi.id 
                 WHERE ma.student_id = s.id 
                 AND ma.status = 'active' 
                 AND ma.assignment_type = 'chair'
                 LIMIT 1) as chair_assigned_date,
                 
                -- Get table description
                (SELECT mi.description 
                 FROM maintenance_assignments ma 
                 JOIN maintenance_items mi ON ma.item_id = mi.id 
                 WHERE ma.student_id = s.id 
                 AND ma.status = 'active' 
                 AND ma.assignment_type = 'table'
                 LIMIT 1) as table_description,
                 
                -- Get chair description
                (SELECT mi.description 
                 FROM maintenance_assignments ma 
                 JOIN maintenance_items mi ON ma.item_id = mi.id 
                 WHERE ma.student_id = s.id 
                 AND ma.status = 'active' 
                 AND ma.assignment_type = 'chair'
                 LIMIT 1) as chair_description,
                 
                -- Check if has any active assignment
                (SELECT COUNT(*) 
                 FROM maintenance_assignments ma 
                 WHERE ma.student_id = s.id 
                 AND ma.status = 'active') as total_assignments
                
            FROM students s";
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    // Apply sorting - Always order by class (Form Five first) then index number
    $sql .= " ORDER BY 
        CASE 
            WHEN s.class = 'Form Five' THEN 1
            WHEN s.class = 'Form Six' THEN 2
            ELSE 3
        END,
        s.index_number ASC";
    
    // Prepare and execute statement
    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $records = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
} else {
    // STAFF REPORT
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    if (!empty($filter_gender)) {
        $where_conditions[] = "a.sex = ?";
        $params[] = $filter_gender;
        $param_types .= 's';
    }
    
    if ($filter_status == 'active') {
        $where_conditions[] = "a.status = 1";
    } elseif ($filter_status == 'inactive') {
        $where_conditions[] = "a.status = 0";
    }
    
    // Filter by assignment status
    if ($assign_status == 'assigned') {
        $where_conditions[] = "EXISTS (
            SELECT 1 FROM maintenance_staff_assignments msa 
            WHERE msa.staff_id = a.id AND msa.status = 'active'
        )";
    } elseif ($assign_status == 'available') {
        $where_conditions[] = "NOT EXISTS (
            SELECT 1 FROM maintenance_staff_assignments msa 
            WHERE msa.staff_id = a.id AND msa.status = 'active'
        )";
    }
    
    // Build base query for staff
    $sql = "SELECT 
                a.id,
                a.first_name,
                a.middle_name,
                a.last_name,
                a.email,
                a.sex,
                a.status,
                a.created_at,
                
                -- Get assigned table
                (SELECT mi.item_code 
                 FROM maintenance_staff_assignments msa 
                 JOIN maintenance_items mi ON msa.item_id = mi.id 
                 WHERE msa.staff_id = a.id 
                 AND msa.status = 'active' 
                 AND msa.assignment_type = 'table'
                 LIMIT 1) as assigned_table,
                 
                -- Get assigned chair
                (SELECT mi.item_code 
                 FROM maintenance_staff_assignments msa 
                 JOIN maintenance_items mi ON msa.item_id = mi.id 
                 WHERE msa.staff_id = a.id 
                 AND msa.status = 'active' 
                 AND msa.assignment_type = 'chair'
                 LIMIT 1) as assigned_chair,
                 
                -- Get table assignment date
                (SELECT msa.assigned_date 
                 FROM maintenance_staff_assignments msa 
                 JOIN maintenance_items mi ON msa.item_id = mi.id 
                 WHERE msa.staff_id = a.id 
                 AND msa.status = 'active' 
                 AND msa.assignment_type = 'table'
                 LIMIT 1) as table_assigned_date,
                 
                -- Get chair assignment date
                (SELECT msa.assigned_date 
                 FROM maintenance_staff_assignments msa 
                 JOIN maintenance_items mi ON msa.item_id = mi.id 
                 WHERE msa.staff_id = a.id 
                 AND msa.status = 'active' 
                 AND msa.assignment_type = 'chair'
                 LIMIT 1) as chair_assigned_date,
                 
                -- Get table description
                (SELECT mi.description 
                 FROM maintenance_staff_assignments msa 
                 JOIN maintenance_items mi ON msa.item_id = mi.id 
                 WHERE msa.staff_id = a.id 
                 AND msa.status = 'active' 
                 AND msa.assignment_type = 'table'
                 LIMIT 1) as table_description,
                 
                -- Get chair description
                (SELECT mi.description 
                 FROM maintenance_staff_assignments msa 
                 JOIN maintenance_items mi ON msa.item_id = mi.id 
                 WHERE msa.staff_id = a.id 
                 AND msa.status = 'active' 
                 AND msa.assignment_type = 'chair'
                 LIMIT 1) as chair_description,
                 
                -- Get staff roles
                GROUP_CONCAT(DISTINCT ar.role_name SEPARATOR ', ') as roles,
                
                -- Check if has any active assignment
                (SELECT COUNT(*) 
                 FROM maintenance_staff_assignments msa 
                 WHERE msa.staff_id = a.id 
                 AND msa.status = 'active') as total_assignments
                
            FROM admins a
            LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
            LEFT JOIN admin_roles ar ON ara.role_id = ar.id";
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " GROUP BY a.id";
    
    // Apply sorting for staff
    switch ($sort_order) {
        case 'name_asc':
            $sql .= " ORDER BY a.first_name ASC, a.last_name ASC";
            break;
        case 'name_desc':
            $sql .= " ORDER BY a.first_name DESC, a.last_name DESC";
            break;
        case 'date_asc':
            $sql .= " ORDER BY a.created_at ASC";
            break;
        case 'date_desc':
            $sql .= " ORDER BY a.created_at DESC";
            break;
        default:
            $sql .= " ORDER BY a.first_name ASC, a.last_name ASC";
    }
    
    // Prepare and execute statement for staff
    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $records = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$total_records = count($records);

// Get statistics for the report
$stats = [
    'total' => $total_records,
    'with_table' => 0,
    'with_chair' => 0,
    'with_both' => 0,
    'without_assignments' => 0,
    'form_five' => 0,
    'form_six' => 0,
    'male' => 0,
    'female' => 0
];

foreach ($records as $record) {
    $has_table = !empty($record['assigned_table']);
    $has_chair = !empty($record['assigned_chair']);
    
    if ($has_table && $has_chair) {
        $stats['with_both']++;
        $stats['with_table']++;
        $stats['with_chair']++;
    } elseif ($has_table) {
        $stats['with_table']++;
    } elseif ($has_chair) {
        $stats['with_chair']++;
    } else {
        $stats['without_assignments']++;
    }
    
    if ($report_type == 'student') {
        if ($record['class'] == 'Form Five') $stats['form_five']++;
        if ($record['class'] == 'Form Six') $stats['form_six']++;
    }
    
    if ($record['sex'] == 'Male') $stats['male']++;
    if ($record['sex'] == 'Female') $stats['female']++;
}

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once('../tcpdf/tcpdf.php');
    
class MaintenancePDF extends TCPDF {
    // Page header
    public function Header() {
        $logo_path = '../muyovozi.png';
        
        // Logo on left
        if (file_exists($logo_path)) {
            $this->Image($logo_path, 15, 10, 25, 25, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        // School name and report title centered
        $this->SetFont('helvetica', 'B', 18);
        $this->SetY(12);
        $this->Cell(0, 0, 'MUYOVOZI HIGH SCHOOL', 0, 1, 'C');
        
        $this->SetFont('helvetica', 'B', 14);
        $this->SetY(22);
        $report_title = $GLOBALS['report_type'] == 'student' ? 'STUDENT MAINTENANCE REPORT' : 'STAFF MAINTENANCE REPORT';
        $this->Cell(0, 0, $report_title, 0, 1, 'C');
        
        // Line separator
        $this->SetY(35);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->SetY($this->GetY() + 8);
    }
    
    // Page footer
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}
    
    // Create PDF
    $pdf = new MaintenancePDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    $pdf->SetCreator('Muyovozi High School');
    $pdf->SetAuthor('Administrator');
    $pdf->SetTitle(($report_type == 'student' ? 'Student' : 'Staff') . ' Maintenance Report');
    $pdf->SetMargins(15, 40, 15); // Reduced top margin since we removed the summary from header
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();
    
    // Group students by class for display
    if ($report_type == 'student') {
        $grouped_students = [];
        foreach ($records as $record) {
            $class = $record['class'] ?: 'Other';
            $grouped_students[$class][] = $record;
        }
        
        // Display Form Five first, then Form Six, then others
        $ordered_classes = ['Form Five', 'Form Six'];
        foreach ($ordered_classes as $class) {
            if (isset($grouped_students[$class]) && count($grouped_students[$class]) > 0) {
                // Add class header
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->SetFillColor(200, 220, 255);
                $pdf->Cell(0, 10, 'CLASS: ' . $class, 0, 1, 'C', true);
                $pdf->Ln(2);
                
                // Display table for this class
                displayStudentTable($pdf, $grouped_students[$class]);
                $pdf->Ln(5);
            }
        }
        
        // Display other classes
        foreach ($grouped_students as $class => $class_students) {
            if (!in_array($class, $ordered_classes) && count($class_students) > 0) {
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->SetFillColor(220, 220, 220);
                $pdf->Cell(0, 10, 'CLASS: ' . $class, 0, 1, 'C', true);
                $pdf->Ln(2);
                
                displayStudentTable($pdf, $class_students);
                $pdf->Ln(5);
            }
        }
    } else {
        // Display staff table
        displayStaffTable($pdf, $records);
    }
    
    // Add statistics section at the end
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'STATISTICS SUMMARY', 0, 1, 'C');
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', '', 10);
    
    $stats_html = '
    <table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%;">
        <tr style="background-color: #3B9DB3; color: white;">
            <th colspan="2" style="text-align: center; font-weight: bold;">OVERALL STATISTICS</th>
        </tr>
        <tr>
            <td style="font-weight: bold; width: 70%;">Total ' . ($report_type == 'student' ? 'Students' : 'Staff') . ':</td>
            <td style="text-align: center; width: 30%;">' . $stats['total'] . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">With Table Assignment:</td>
            <td style="text-align: center;">' . $stats['with_table'] . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">With Chair Assignment:</td>
            <td style="text-align: center;">' . $stats['with_chair'] . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">With Both Table & Chair:</td>
            <td style="text-align: center;">' . $stats['with_both'] . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Without Assignments:</td>
            <td style="text-align: center;">' . $stats['without_assignments'] . '</td>
        </tr>';
    
    if ($report_type == 'student') {
        $stats_html .= '
        <tr>
            <td style="font-weight: bold;">Form Five Students:</td>
            <td style="text-align: center;">' . $stats['form_five'] . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Form Six Students:</td>
            <td style="text-align: center;">' . $stats['form_six'] . '</td>
        </tr>';
    }
    
    $stats_html .= '
        <tr>
            <td style="font-weight: bold;">Male:</td>
            <td style="text-align: center;">' . $stats['male'] . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Female:</td>
            <td style="text-align: center;">' . $stats['female'] . '</td>
        </tr>
    </table>';
    
    $pdf->writeHTML($stats_html, true, false, true, false, '');
    
    $pdf->Ln(10);
    
    // Add Report Summary section after Statistics
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'REPORT SUMMARY', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Create PDF summary table HTML
    $summary_html = '
    <table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-size: 10pt;">
        <tr style="background-color: #3B9DB3; color: white;">
            <th colspan="4" style="text-align: center; font-weight: bold;">REPORT PARAMETERS</th>
        </tr>
        <tr>
            <td style="font-weight: bold; width: 25%;">Report Type:</td>
            <td style="width: 25%;">' . ($report_type == 'student' ? 'Student Maintenance' : 'Staff Maintenance') . '</td>
            <td style="font-weight: bold; width: 25%;">Generated Date:</td>
            <td style="width: 25%;">' . date('d/m/Y') . '</td>
        </tr>';
    
    if ($report_type == 'student') {
        $summary_html .= '
        <tr>
            <td style="font-weight: bold;">Class:</td>
            <td>' . ($filter_class ? htmlspecialchars($filter_class) : 'All Classes') . '</td>
            <td style="font-weight: bold;">Combination:</td>
            <td>' . ($filter_combination ? htmlspecialchars($filter_combination) : 'All Combinations') . '</td>
        </tr>';
    }
    
    $summary_html .= '
        <tr>
            <td style="font-weight: bold;">Gender:</td>
            <td>' . ($filter_gender ? htmlspecialchars($filter_gender) : 'All') . '</td>
            <td style="font-weight: bold;">Status:</td>
            <td>' . ucfirst($filter_status) . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Assignment Status:</td>
            <td>' . ucfirst($assign_status) . '</td>
            <td style="font-weight: bold;">Total Records:</td>
            <td>' . $total_records . '</td>
        </tr>
    </table>';
    
    $pdf->writeHTML($summary_html, true, false, true, false, '');
    
    // Output PDF
    $pdf->Output('maintenance_report_' . date('Y-m-d') . '.pdf', 'D');
    exit();
}

// Function to display student table in PDF
function displayStudentTable($pdf, $students) {
    global $include_class, $include_combination, $include_gender, $include_table, $include_chair;
    
    // Calculate column widths
    $col_widths = [12, 25, 60]; // S/N, Index No., Full Name
    
    if ($include_class) $col_widths[] = 20;
    if ($include_combination) $col_widths[] = 25;
    if ($include_gender) $col_widths[] = 18;
    if ($include_table) $col_widths[] = 25;
    if ($include_chair) $col_widths[] = 25;
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(59, 157, 179);
    $pdf->SetTextColor(255);
    $pdf->SetDrawColor(59, 157, 179);
    $pdf->SetLineWidth(0.3);
    
    $headers = ['S/N', 'Index No.', 'Full Name'];
    if ($include_class) $headers[] = 'Class';
    if ($include_combination) $headers[] = 'Combination';
    if ($include_gender) $headers[] = 'Gender';
    if ($include_table) $headers[] = 'Table';
    if ($include_chair) $headers[] = 'Chair';
    
    // Output headers
    for($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($col_widths[$i], 8, $headers[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Table content
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', '', 9);
    $fill = false;
    $sn = 1;
    
    foreach($students as $student) {
        if($fill) {
            $pdf->SetFillColor(240, 248, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        // S/N
        $pdf->Cell($col_widths[0], 8, $sn, 1, 0, 'C', $fill);
        
        // Index Number
        $index = $student['index_number'] ?: 'N/A';
        $pdf->Cell($col_widths[1], 8, $index, 1, 0, 'C', $fill);
        
        // Full Name
        $full_name = $student['first_name'] . ' ' . $student['last_name'];
        $pdf->Cell($col_widths[2], 8, $full_name, 1, 0, 'L', $fill);
        
        $col_index = 3;
        
        // Class
        if ($include_class) {
            $pdf->Cell($col_widths[$col_index], 8, $student['class'] ?: 'N/A', 1, 0, 'C', $fill);
            $col_index++;
        }
        
        // Combination
        if ($include_combination) {
            $pdf->Cell($col_widths[$col_index], 8, $student['combination'] ?: 'N/A', 1, 0, 'C', $fill);
            $col_index++;
        }
        
        // Gender
        if ($include_gender) {
            $pdf->Cell($col_widths[$col_index], 8, $student['sex'] ?: 'N/A', 1, 0, 'C', $fill);
            $col_index++;
        }
        
        // Table
        if ($include_table) {
            $table_text = $student['assigned_table'] ?: 'Not assigned';
            $pdf->Cell($col_widths[$col_index], 8, $table_text, 1, 0, 'C', $fill);
            $col_index++;
        }
        
        // Chair
        if ($include_chair) {
            $chair_text = $student['assigned_chair'] ?: 'Not assigned';
            $pdf->Cell($col_widths[$col_index], 8, $chair_text, 1, 0, 'C', $fill);
        }
        
        $pdf->Ln();
        $fill = !$fill;
        $sn++;
    }
}

// Function to display staff table in PDF
function displayStaffTable($pdf, $staff) {
    global $include_email, $include_gender, $include_role, $include_table, $include_chair;
    
    // Calculate column widths
    $col_widths = [12, 20, 60]; // S/N, ID, Full Name
    
    if ($include_email) $col_widths[] = 45;
    if ($include_gender) $col_widths[] = 18;
    if ($include_role) $col_widths[] = 30;
    if ($include_table) $col_widths[] = 25;
    if ($include_chair) $col_widths[] = 25;
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(59, 157, 179);
    $pdf->SetTextColor(255);
    $pdf->SetDrawColor(59, 157, 179);
    $pdf->SetLineWidth(0.3);
    
    $headers = ['S/N', 'ID', 'Full Name'];
    if ($include_email) $headers[] = 'Email';
    if ($include_gender) $headers[] = 'Gender';
    if ($include_role) $headers[] = 'Role';
    if ($include_table) $headers[] = 'Table';
    if ($include_chair) $headers[] = 'Chair';
    
    // Output headers
    for($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($col_widths[$i], 8, $headers[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Table content
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', '', 9);
    $fill = false;
    $sn = 1;
    
    foreach($staff as $person) {
        if($fill) {
            $pdf->SetFillColor(240, 248, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        // S/N
        $pdf->Cell($col_widths[0], 8, $sn, 1, 0, 'C', $fill);
        
        // ID
        $pdf->Cell($col_widths[1], 8, $person['id'], 1, 0, 'C', $fill);
        
        // Full Name
        $full_name = $person['first_name'] . ' ' . ($person['middle_name'] ? $person['middle_name'] . ' ' : '') . $person['last_name'];
        $pdf->Cell($col_widths[2], 8, $full_name, 1, 0, 'L', $fill);
        
        $col_index = 3;
        
        // Email
        if ($include_email) {
            $pdf->Cell($col_widths[$col_index], 8, $person['email'] ?: 'N/A', 1, 0, 'L', $fill);
            $col_index++;
        }
        
        // Gender
        if ($include_gender) {
            $pdf->Cell($col_widths[$col_index], 8, $person['sex'] ?: 'N/A', 1, 0, 'C', $fill);
            $col_index++;
        }
        
        // Role
        if ($include_role) {
            $pdf->Cell($col_widths[$col_index], 8, $person['roles'] ?: 'N/A', 1, 0, 'L', $fill);
            $col_index++;
        }
        
        // Table
        if ($include_table) {
            $table_text = $person['assigned_table'] ?: 'Not assigned';
            $pdf->Cell($col_widths[$col_index], 8, $table_text, 1, 0, 'C', $fill);
            $col_index++;
        }
        
        // Chair
        if ($include_chair) {
            $chair_text = $person['assigned_chair'] ?: 'Not assigned';
            $pdf->Cell($col_widths[$col_index], 8, $chair_text, 1, 0, 'C', $fill);
        }
        
        $pdf->Ln();
        $fill = !$fill;
        $sn++;
    }
}

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="maintenance_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<table border="1">';
    echo '<tr><th colspan="' . (3 + ($include_class ? 1 : 0) + ($include_combination ? 1 : 0) + ($include_gender ? 1 : 0) + ($include_email ? 1 : 0) + ($include_role ? 1 : 0) + ($include_table ? 1 : 0) + ($include_chair ? 1 : 0)) . '">';
    echo 'MUYOVOZI HIGH SCHOOL - ' . ($report_type == 'student' ? 'STUDENT' : 'STAFF') . ' MAINTENANCE REPORT';
    echo '</th></tr>';
    
    echo '<tr>';
    echo '<th>S/N</th>';
    echo '<th>' . ($report_type == 'student' ? 'Index No.' : 'ID') . '</th>';
    echo '<th>Full Name</th>';
    
    if ($report_type == 'student') {
        if ($include_class) echo '<th>Class</th>';
        if ($include_combination) echo '<th>Combination</th>';
        if ($include_gender) echo '<th>Gender</th>';
    } else {
        if ($include_email) echo '<th>Email</th>';
        if ($include_gender) echo '<th>Gender</th>';
        if ($include_role) echo '<th>Role</th>';
    }
    
    if ($include_table) echo '<th>Table</th>';
    if ($include_chair) echo '<th>Chair</th>';
    echo '</tr>';
    
    $sn = 1;
    foreach($records as $record) {
        echo '<tr>';
        echo '<td>' . $sn . '</td>';
        echo '<td>' . ($report_type == 'student' ? ($record['index_number'] ?: 'N/A') : $record['id']) . '</td>';
        echo '<td>' . ($report_type == 'student' ? $record['first_name'] . ' ' . $record['last_name'] : $record['first_name'] . ' ' . ($record['middle_name'] ? $record['middle_name'] . ' ' : '') . $record['last_name']) . '</td>';
        
        if ($report_type == 'student') {
            if ($include_class) echo '<td>' . ($record['class'] ?: 'N/A') . '</td>';
            if ($include_combination) echo '<td>' . ($record['combination'] ?: 'N/A') . '</td>';
            if ($include_gender) echo '<td>' . ($record['sex'] ?: 'N/A') . '</td>';
        } else {
            if ($include_email) echo '<td>' . ($record['email'] ?: 'N/A') . '</td>';
            if ($include_gender) echo '<td>' . ($record['sex'] ?: 'N/A') . '</td>';
            if ($include_role) echo '<td>' . ($record['roles'] ?: 'N/A') . '</td>';
        }
        
        if ($include_table) echo '<td>' . ($record['assigned_table'] ?: 'Not assigned') . '</td>';
        if ($include_chair) echo '<td>' . ($record['assigned_chair'] ?: 'Not assigned') . '</td>';
        echo '</tr>';
        $sn++;
    }
    
    echo '</table>';
    exit();
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">Maintenance Report Generator</h2>
            <!-- Action Button with Dropdown -->
                <div class="dropdown">
                    
                    <button class="btn btn-primary dropdown-toggle" type="button" id="actionDropdown" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cog me-2"></i>Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionDropdown">
                        <li><a class="dropdown-item" href="maintenance.php">
                            <i class="fas fa-tools me-2"></i>Dashboard
                        </a></li>
                       
                        <li><a class="dropdown-item" href="student_main.php">
                            <i class="fas fa-user-graduate me-2"></i>Assign Student
                        </a></li>
                        <li><a class="dropdown-item" href="staff_main.php">
                            <i class="fas fa-chalkboard-teacher me-2"></i>Assign Staff
                        </a></li>
                        <li><a class="dropdown-item" href="maintenance_logs.php">
                            <i class="fas fa-history me-2"></i>View Logs
                        </a></li>
                        <li><a class="dropdown-item" href="report_maintenance.php"><i class="fas fa-download me-2"></i>Export List</a></li>
                    </ul>
                </div>
        </div>

        <!-- Report Type Tabs -->
        <div class="card mb-4">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="reportTypeTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $report_type == 'student' ? 'active' : ''; ?>" 
                                id="student-tab" data-bs-toggle="tab" data-bs-target="#student-tab-pane" 
                                type="button" role="tab" onclick="switchReportType('student')">
                            <i class="fas fa-user-graduate me-2"></i>Student Report
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $report_type == 'staff' ? 'active' : ''; ?>" 
                                id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff-tab-pane" 
                                type="button" role="tab" onclick="switchReportType('staff')">
                            <i class="fas fa-chalkboard-teacher me-2"></i>Staff Report
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" action="report_maintenance.php" id="filterForm">
                    <input type="hidden" name="report_type" id="report_type" value="<?php echo $report_type; ?>">
                    
                    <div class="row">
                        <!-- Student-specific filters -->
                        <div id="studentFilters" class="<?php echo $report_type == 'student' ? '' : 'd-none'; ?>">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Class</label>
                                <select name="class" class="form-select">
                                    <option value="">All Classes</option>
                                    <?php foreach($classes as $class): ?>
                                    <option value="<?php echo htmlspecialchars($class); ?>" 
                                            <?php echo $filter_class == $class ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Combination</label>
                                <select name="combination" class="form-select">
                                    <option value="">All Combinations</option>
                                    <?php foreach($combinations as $comb): ?>
                                    <option value="<?php echo htmlspecialchars($comb); ?>" 
                                            <?php echo $filter_combination == $comb ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($comb); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Common filters -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">All Genders</option>
                                <option value="Male" <?php echo $filter_gender == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $filter_gender == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $filter_status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="both" <?php echo $filter_status == 'both' ? 'selected' : ''; ?>>Both</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Assignment Status</label>
                            <select name="assign_status" class="form-select">
                                <option value="all" <?php echo $assign_status == 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="assigned" <?php echo $assign_status == 'assigned' ? 'selected' : ''; ?>>With Assignments</option>
                                <option value="available" <?php echo $assign_status == 'available' ? 'selected' : ''; ?>>Without Assignments</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Sort Order</label>
                            <select name="sort_order" class="form-select">
                                <?php if ($report_type == 'student'): ?>
                                <option value="index_asc" <?php echo $sort_order == 'index_asc' ? 'selected' : ''; ?>>Index Number (Ascending)</option>
                                <option value="index_desc" <?php echo $sort_order == 'index_desc' ? 'selected' : ''; ?>>Index Number (Descending)</option>
                                <option value="name_asc" <?php echo $sort_order == 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                                <option value="name_desc" <?php echo $sort_order == 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                                <?php else: ?>
                                <option value="name_asc" <?php echo $sort_order == 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                                <option value="name_desc" <?php echo $sort_order == 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                                <option value="date_asc" <?php echo $sort_order == 'date_asc' ? 'selected' : ''; ?>>Date Created (Oldest)</option>
                                <option value="date_desc" <?php echo $sort_order == 'date_desc' ? 'selected' : ''; ?>>Date Created (Newest)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Column Inclusion Options -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h6 class="mb-3">Select Columns to Include:</h6>
                            <div class="row">
                                <!-- Common columns -->
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_table" value="1" 
                                               id="includeTable" <?php echo $include_table ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeTable">
                                            Include Table
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_chair" value="1" 
                                               id="includeChair" <?php echo $include_chair ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeChair">
                                            Include Chair
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_gender" value="1" 
                                               id="includeGender" <?php echo $include_gender ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeGender">
                                            Include Gender
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Student-specific columns -->
                                <div id="studentColumns" class="<?php echo $report_type == 'student' ? '' : 'd-none'; ?>">
                                    <div class="col-md-2 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="include_class" value="1" 
                                                   id="includeClass" <?php echo $include_class ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="includeClass">
                                                Include Class
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="include_combination" value="1" 
                                                   id="includeCombination" <?php echo $include_combination ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="includeCombination">
                                                Include Combination
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Staff-specific columns -->
                                <div id="staffColumns" class="<?php echo $report_type == 'staff' ? '' : 'd-none'; ?>">
                                    <div class="col-md-2 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="include_email" value="1" 
                                                   id="includeEmail" <?php echo $include_email ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="includeEmail">
                                                Include Email
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="include_role" value="1" 
                                                   id="includeRole" <?php echo $include_role ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="includeRole">
                                                Include Role
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-info">Found: <?php echo $total_records; ?> records</span>
                                    <span class="badge bg-secondary ms-2">
                                        <?php 
                                        if ($report_type == 'student') {
                                            echo 'Sorted: Form Five → Form Six → Index No.';
                                        } else {
                                            echo $sort_order == 'name_asc' ? 'Sorted by Name' : 'Sorted by Date';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search me-2"></i>Apply Filters
                                    </button>
                                    <a href="report_maintenance.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-redo me-2"></i>Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas <?php echo $report_type == 'student' ? 'fa-user-graduate' : 'fa-chalkboard-teacher'; ?>" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total <?php echo $report_type == 'student' ? 'Students' : 'Staff'; ?></p>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-table" style="color: #28a745;"></i>
                    </div>
                    <h3><?php echo $stats['with_table']; ?></h3>
                    <p>With Table</p>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-chair" style="color: #17a2b8;"></i>
                    </div>
                    <h3><?php echo $stats['with_chair']; ?></h3>
                    <p>With Chair</p>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-tasks" style="color: #ffc107;"></i>
                    </div>
                    <h3><?php echo $stats['with_both']; ?></h3>
                    <p>With Both</p>
                </div>
            </div>
        </div>

        <!-- Export Options -->
        <div class="card mb-4">
            <div class="card-header" style="background-color: #3B9DB3; color: white;">
                <h4 class="mb-0">
                    <i class="fas fa-download me-2"></i>Export Options
                </h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="export-option text-center p-4 mb-3" style="border: 2px dashed #dc3545; border-radius: 10px;">
                            <i class="fas fa-file-pdf fa-3x mb-3" style="color: #dc3545;"></i>
                            <h4>Export as PDF</h4>
                            <p class="text-muted">Professional PDF report with school logo</p>
                            <?php
                            $export_url = "report_maintenance.php?" . http_build_query(array_merge($_GET, ['export' => 'pdf']));
                            ?>
                            <a href="<?php echo $export_url; ?>" class="btn btn-danger btn-lg">
                                <i class="fas fa-download me-2"></i>Download PDF
                            </a>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="export-option text-center p-4 mb-3" style="border: 2px dashed #28a745; border-radius: 10px;">
                            <i class="fas fa-file-excel fa-3x mb-3" style="color: #28a745;"></i>
                            <h4>Export as Excel</h4>
                            <p class="text-muted">Excel spreadsheet for data analysis</p>
                            <?php
                            $export_excel_url = "report_maintenance.php?" . http_build_query(array_merge($_GET, ['export' => 'excel']));
                            ?>
                            <a href="<?php echo $export_excel_url; ?>" class="btn btn-success btn-lg">
                                <i class="fas fa-download me-2"></i>Download Excel
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>PDF Report Features:</strong> 
                    <ul class="mb-0">
                        <li>School logo on left, title centered</li>
                        <li>Students grouped by class (Form Five first)</li>
                        <li>Class headers above each section</li>
                        <li>Statistics summary on last page</li>
                        <li>Report summary after statistics</li>
                        <li>Professional formatting</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Preview Section -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-eye me-2"></i>Report Preview
                    </h4>
                    <div>
                        <button class="btn btn-sm btn-light" onclick="window.print()">
                            <i class="fas fa-print me-1"></i>Print Preview
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Report Summary Preview -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Report Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <tbody>
                                    <tr>
                                        <th width="25%">Report Type:</th>
                                        <td width="25%"><?php echo $report_type == 'student' ? 'Student Maintenance' : 'Staff Maintenance'; ?></td>
                                        <th width="25%">Generated Date:</th>
                                        <td width="25%"><?php echo date('d/m/Y'); ?></td>
                                    </tr>
                                    <?php if ($report_type == 'student'): ?>
                                    <tr>
                                        <th>Class:</th>
                                        <td><?php echo $filter_class ? htmlspecialchars($filter_class) : 'All Classes'; ?></td>
                                        <th>Combination:</th>
                                        <td><?php echo $filter_combination ? htmlspecialchars($filter_combination) : 'All Combinations'; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th>Gender:</th>
                                        <td><?php echo $filter_gender ? htmlspecialchars($filter_gender) : 'All'; ?></td>
                                        <th>Status:</th>
                                        <td><?php echo ucfirst($filter_status); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Assignment Status:</th>
                                        <td><?php echo ucfirst($assign_status); ?></td>
                                        <th>Total Records:</th>
                                        <td><?php echo $total_records; ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php if ($total_records > 0): ?>
                <!-- Group students by class for preview -->
                <?php if ($report_type == 'student'): 
                    $grouped_students = [];
                    foreach ($records as $record) {
                        $class = $record['class'] ?: 'Other';
                        $grouped_students[$class][] = $record;
                    }
                    $ordered_classes = ['Form Five', 'Form Six'];
                ?>
                
                <?php foreach ($ordered_classes as $class): ?>
                    <?php if (isset($grouped_students[$class]) && count($grouped_students[$class]) > 0): ?>
                    <div class="class-section mb-4">
                        <div class="alert alert-secondary mb-3">
                            <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>CLASS: <?php echo $class; ?></h5>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>S/N</th>
                                        <th>Index No.</th>
                                        <th>Full Name</th>
                                        <?php if ($include_class): ?><th>Class</th><?php endif; ?>
                                        <?php if ($include_combination): ?><th>Combination</th><?php endif; ?>
                                        <?php if ($include_gender): ?><th>Gender</th><?php endif; ?>
                                        <?php if ($include_table): ?><th>Table</th><?php endif; ?>
                                        <?php if ($include_chair): ?><th>Chair</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($grouped_students[$class] as $index => $student): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($student['index_number'] ?: 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        <?php if ($include_class): ?>
                                        <td><span class="badge bg-success"><?php echo htmlspecialchars($student['class'] ?: 'N/A'); ?></span></td>
                                        <?php endif; ?>
                                        <?php if ($include_combination): ?>
                                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($student['combination'] ?: 'N/A'); ?></span></td>
                                        <?php endif; ?>
                                        <?php if ($include_gender): ?>
                                        <td><span class="badge <?php echo $student['sex'] == 'Male' ? 'bg-info' : 'bg-pink'; ?>"><?php echo htmlspecialchars($student['sex'] ?: 'N/A'); ?></span></td>
                                        <?php endif; ?>
                                        <?php if ($include_table): ?>
                                        <td>
                                            <?php if ($student['assigned_table']): ?>
                                                <span class="badge bg-success"><?php echo htmlspecialchars($student['assigned_table']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                        <?php if ($include_chair): ?>
                                        <td>
                                            <?php if ($student['assigned_chair']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($student['assigned_chair']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <!-- Other classes -->
                <?php foreach ($grouped_students as $class => $class_students): ?>
                    <?php if (!in_array($class, $ordered_classes) && count($class_students) > 0): ?>
                    <div class="class-section mb-4">
                        <div class="alert alert-warning mb-3">
                            <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>CLASS: <?php echo $class; ?></h5>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>S/N</th>
                                        <th>Index No.</th>
                                        <th>Full Name</th>
                                        <?php if ($include_class): ?><th>Class</th><?php endif; ?>
                                        <?php if ($include_combination): ?><th>Combination</th><?php endif; ?>
                                        <?php if ($include_gender): ?><th>Gender</th><?php endif; ?>
                                        <?php if ($include_table): ?><th>Table</th><?php endif; ?>
                                        <?php if ($include_chair): ?><th>Chair</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($class_students as $index => $student): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($student['index_number'] ?: 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        <?php if ($include_class): ?>
                                        <td><span class="badge bg-success"><?php echo htmlspecialchars($student['class'] ?: 'N/A'); ?></span></td>
                                        <?php endif; ?>
                                        <?php if ($include_combination): ?>
                                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($student['combination'] ?: 'N/A'); ?></span></td>
                                        <?php endif; ?>
                                        <?php if ($include_gender): ?>
                                        <td><span class="badge <?php echo $student['sex'] == 'Male' ? 'bg-info' : 'bg-pink'; ?>"><?php echo htmlspecialchars($student['sex'] ?: 'N/A'); ?></span></td>
                                        <?php endif; ?>
                                        <?php if ($include_table): ?>
                                        <td>
                                            <?php if ($student['assigned_table']): ?>
                                                <span class="badge bg-success"><?php echo htmlspecialchars($student['assigned_table']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                        <?php if ($include_chair): ?>
                                        <td>
                                            <?php if ($student['assigned_chair']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($student['assigned_chair']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <?php else: ?>
                <!-- Staff Preview -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="previewTable">
                        <thead class="table-light">
                            <tr>
                                <th>S/N</th>
                                <th>ID</th>
                                <th>Full Name</th>
                                <?php if ($include_email): ?><th>Email</th><?php endif; ?>
                                <?php if ($include_gender): ?><th>Gender</th><?php endif; ?>
                                <?php if ($include_role): ?><th>Role</th><?php endif; ?>
                                <?php if ($include_table): ?><th>Table</th><?php endif; ?>
                                <?php if ($include_chair): ?><th>Chair</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($records as $index => $record): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($record['id']); ?></strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3">
                                            <?php if ($record['sex'] == 'Male'): ?>
                                                <i class="fas fa-male text-primary"></i>
                                            <?php else: ?>
                                                <i class="fas fa-female" style="color: #e83e8c;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . ($record['middle_name'] ? $record['middle_name'] . ' ' : '') . $record['last_name']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <?php if ($include_email): ?>
                                <td><?php echo htmlspecialchars($record['email'] ?: 'N/A'); ?></td>
                                <?php endif; ?>
                                <?php if ($include_gender): ?>
                                <td>
                                    <span class="badge <?php echo $record['sex'] == 'Male' ? 'bg-info' : 'bg-pink'; ?>">
                                        <?php echo htmlspecialchars($record['sex'] ?: 'N/A'); ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <?php if ($include_role): ?>
                                <td><?php echo htmlspecialchars($record['roles'] ?: 'N/A'); ?></td>
                                <?php endif; ?>
                                <?php if ($include_table): ?>
                                <td>
                                    <?php if ($record['assigned_table']): ?>
                                        <span class="badge bg-success"><?php echo htmlspecialchars($record['assigned_table']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <?php if ($include_chair): ?>
                                <td>
                                    <?php if ($record['assigned_chair']): ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($record['assigned_chair']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h4>No records found</h4>
                    <p class="text-muted">Try adjusting your filter criteria</p>
                    <a href="report_maintenance.php" class="btn btn-primary">
                        <i class="fas fa-redo me-2"></i>Reset Filters
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to switch report type
    window.switchReportType = function(type) {
        document.getElementById('report_type').value = type;
        
        // Show/hide appropriate filters
        const studentFilters = document.getElementById('studentFilters');
        const staffFilters = document.getElementById('staffFilters');
        const studentColumns = document.getElementById('studentColumns');
        const staffColumns = document.getElementById('staffColumns');
        
        if (type === 'student') {
            studentFilters.classList.remove('d-none');
            staffFilters.classList.add('d-none');
            studentColumns.classList.remove('d-none');
            staffColumns.classList.add('d-none');
        } else {
            studentFilters.classList.add('d-none');
            staffFilters.classList.remove('d-none');
            studentColumns.classList.add('d-none');
            staffColumns.classList.remove('d-none');
        }
        
        // Update sort options
        const sortSelect = document.querySelector('select[name="sort_order"]');
        if (type === 'student') {
            sortSelect.innerHTML = `
                <option value="index_asc">Index Number (Ascending)</option>
                <option value="index_desc">Index Number (Descending)</option>
                <option value="name_asc">Name (A-Z)</option>
                <option value="name_desc">Name (Z-A)</option>
            `;
        } else {
            sortSelect.innerHTML = `
                <option value="name_asc">Name (A-Z)</option>
                <option value="name_desc">Name (Z-A)</option>
                <option value="date_asc">Date Created (Oldest)</option>
                <option value="date_desc">Date Created (Newest)</option>
            `;
        }
    };
    
    // Update checkbox labels
    const checkboxes = document.querySelectorAll('.form-check-input');
    checkboxes.forEach(checkbox => {
        const label = checkbox.nextElementSibling;
        checkbox.addEventListener('change', function() {
            if (label) {
                label.textContent = this.checked ? 
                    label.textContent.replace('Include', 'Including') : 
                    label.textContent.replace('Including', 'Include');
            }
        });
        
        // Initialize label text
        if (label && checkbox.checked) {
            label.textContent = label.textContent.replace('Include', 'Including');
        }
    });
    
    // Auto-submit on some filter changes
    const autoSubmitFilters = ['class', 'combination', 'gender', 'status', 'assign_status', 'sort_order'];
    autoSubmitFilters.forEach(filterName => {
        const element = document.querySelector(`[name="${filterName}"]`);
        if (element) {
            element.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        }
    });
    
    // Toggle all columns button
    const toggleAllBtn = document.createElement('button');
    toggleAllBtn.type = 'button';
    toggleAllBtn.className = 'btn btn-sm btn-outline-secondary mb-3';
    toggleAllBtn.innerHTML = '<i class="fas fa-toggle-on me-1"></i>Toggle All Columns';
    toggleAllBtn.addEventListener('click', function() {
        const currentType = document.getElementById('report_type').value;
        const checkboxes = document.querySelectorAll('.form-check-input');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(cb => {
            // Only toggle checkboxes that are visible for current report type
            const parentDiv = cb.closest('.col-md-2');
            if (parentDiv && !parentDiv.classList.contains('d-none')) {
                cb.checked = !allChecked;
                cb.dispatchEvent(new Event('change'));
            }
        });
        
        this.innerHTML = allChecked ? 
            '<i class="fas fa-toggle-on me-1"></i>Select All Columns' : 
            '<i class="fas fa-toggle-off me-1"></i>Deselect All Columns';
    });
    
    const columnOptionsDiv = document.querySelector('.row.mb-4 .col-md-12');
    if (columnOptionsDiv) {
        columnOptionsDiv.insertBefore(toggleAllBtn, columnOptionsDiv.querySelector('h6'));
    }
    
    // Add hover effects to table rows
    const tableRows = document.querySelectorAll('table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgba(59, 157, 179, 0.05)';
            this.style.cursor = 'pointer';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
    
    // Initialize based on current report type
    switchReportType('<?php echo $report_type; ?>');
});
</script>

<style>
/* Custom styles for maintenance report page */
.card-header{
     background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);
}
.avatar-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: rgba(59, 157, 179, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.bg-pink {
    background-color: #e83e8c !important;
    color: white;
}

.stats-card.simple-card {
    border: none;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    background: white;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    height: 100%;
}

.stats-card.simple-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 15px rgba(0,0,0,0.1);
}

.stats-card.simple-card .stats-icon i {
    font-size: 2rem;
    margin-bottom: 10px;
}

.stats-card.simple-card h3 {
    font-size: 1.8rem;
    font-weight: bold;
    margin: 10px 0 5px 0;
}

.stats-card.simple-card p {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
}

.export-option {
    transition: transform 0.3s ease;
}

.export-option:hover {
    transform: translateY(-5px);
}

.nav-tabs .nav-link {
    color: rgba(255, 255, 255, 0.8);
    border: none;
    padding: 10px 20px;
}

.nav-tabs .nav-link.active {
    color: #3B9DB3;
    background-color: white;
    border-bottom: 3px solid #3B9DB3;
}

.nav-tabs .nav-link:hover:not(.active) {
    color: white;
    background-color: rgba(255, 255, 255, 0.1);
}

.class-section {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    background-color: #f8f9fa;
}

.class-section .alert {
    border-radius: 6px;
    margin-bottom: 15px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .stats-card.simple-card {
        padding: 15px;
        margin-bottom: 10px;
    }
    
    .stats-card.simple-card h3 {
        font-size: 1.5rem;
    }
    
    .btn-lg {
        padding: 10px 20px;
        font-size: 1rem;
    }
    
    .export-option {
        margin-bottom: 15px;
    }
    
    .table-responsive {
        font-size: 0.85rem;
    }
    
    .avatar-circle {
        width: 30px;
        height: 30px;
        margin-right: 8px;
    }
    
    .nav-tabs .nav-link {
        padding: 8px 12px;
        font-size: 0.9rem;
    }
    
    .class-section {
        padding: 10px;
    }
}

/* Print styles */
@media print {
    .no-print, .card-header .btn, .export-option, .stats-card, .nav-tabs, form {
        display: none !important;
    }
    
    .card, .card-body {
        border: none !important;
        box-shadow: none !important;
    }
    
    .table th {
        background-color: #f8f9fa !important;
        color: #000 !important;
    }
    
    .class-section {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}

/* Column options styling */
.form-check {
    padding-left: 2rem;
    margin-bottom: 0.5rem;
}

.form-check-input:checked {
    background-color: #3B9DB3;
    border-color: #3B9DB3;
}

.form-check-label {
    cursor: pointer;
    font-weight: 500;
}

/* Summary table styling */
.table-sm th, .table-sm td {
    padding: 8px;
}

.table-bordered {
    border: 1px solid #dee2e6;
}

.table-bordered th {
    background-color: #f8f9fa;
    font-weight: 600;
}
</style>

<?php include '../controller/footer.php'; ?>