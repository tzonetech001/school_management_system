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
    if ($role_id == 1 || $role_id == 2 || $role_id == 7) { // Head Master or Second Master
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view staff members.";
    header("Location:  ../404.php");
    exit();
}


// Get all roles from database (excluding Super Admin if it exists)
$roles = [];
$roles_sql = "SELECT * FROM admin_roles WHERE role_name != 'Super Admin' ORDER BY role_name";
$roles_result = mysqli_query($conn, $roles_sql);
if ($roles_result && mysqli_num_rows($roles_result) > 0) {
    while ($row = mysqli_fetch_assoc($roles_result)) {
        $roles[] = $row;
    }
}

// Default values for filters
$filter_gender = $_GET['gender'] ?? 'All'; // All, Male, Female
$filter_dormitory = $_GET['dormitory'] ?? 'All';
$filter_room = $_GET['room'] ?? 'All';
$filter_class = $_GET['class'] ?? 'All'; // All, Form Five, Form Six
$filter_combination = $_GET['combination'] ?? 'All';
$filter_status = $_GET['status'] ?? 'Active'; // Active, Inactive, All

// Column inclusion options
$include_index = isset($_GET['include_index']) && $_GET['include_index'] == 1 ? true : false;
$include_combination = isset($_GET['include_combination']) && $_GET['include_combination'] == 1 ? true : false;
$include_class = isset($_GET['include_class']) && $_GET['include_class'] == 1 ? true : false;
$include_dormitory = isset($_GET['include_dormitory']) && $_GET['include_dormitory'] == 1 ? true : false;
$include_room = isset($_GET['include_room']) && $_GET['include_room'] == 1 ? true : false;
$include_bed = isset($_GET['include_bed']) && $_GET['include_bed'] == 1 ? true : false;
$include_status = isset($_GET['include_status']) && $_GET['include_status'] == 1 ? true : false;
$include_gender = isset($_GET['include_gender']) && $_GET['include_gender'] == 1 ? true : false;
$include_date = isset($_GET['include_date']) && $_GET['include_date'] == 1 ? true : false;

// Get dormitories for dropdown
$dormitories_sql = "SELECT id, dorm_name, dorm_type FROM dormitories ORDER BY dorm_type, dorm_name";
$dormitories_result = mysqli_query($conn, $dormitories_sql);
$dormitories = [];
while ($row = mysqli_fetch_assoc($dormitories_result)) {
    $dormitories[] = $row;
}

// Get combinations for dropdown
$combinations_sql = "SELECT DISTINCT combination FROM students WHERE combination IS NOT NULL AND combination != '' ORDER BY combination";
$combinations_result = mysqli_query($conn, $combinations_sql);
$combinations = [];
while ($row = mysqli_fetch_assoc($combinations_result)) {
    $combinations[] = $row['combination'];
}

// Build SQL query based on filters
$where_conditions = ["sd.status = 'Active'"];
$params = [];
$param_types = "";

// Gender filter
if ($filter_gender != 'All') {
    $where_conditions[] = "s.sex = ?";
    $params[] = $filter_gender;
    $param_types .= "s";
}

// Dormitory filter
if ($filter_dormitory != 'All') {
    $where_conditions[] = "d.id = ?";
    $params[] = $filter_dormitory;
    $param_types .= "s";
}

// Room filter
if ($filter_room != 'All') {
    $where_conditions[] = "dr.id = ?";
    $params[] = $filter_room;
    $param_types .= "s";
}

// Class filter
if ($filter_class != 'All') {
    $where_conditions[] = "s.class = ?";
    $params[] = $filter_class;
    $param_types .= "s";
}

// Combination filter
if ($filter_combination != 'All') {
    $where_conditions[] = "s.combination = ?";
    $params[] = $filter_combination;
    $param_types .= "s";
}

// Student status filter
if ($filter_status != 'All') {
    if ($filter_status == 'Active') {
        $where_conditions[] = "s.status = 1";
    } else if ($filter_status == 'Inactive') {
        $where_conditions[] = "s.status = 0";
    }
}

// Only non-leavers
$where_conditions[] = "s.is_leaver = FALSE";

$where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get filtered dormitory assignments
$sql = "SELECT 
    s.id as student_id,
    s.index_number,
    s.first_name,
    s.last_name,
    s.second_name,
    s.sex,
    s.class,
    s.combination,
    s.status as student_status,
    s.date_of_birth,
    s.admission_number,
    d.id as dormitory_id,
    d.dorm_name,
    d.dorm_type,
    dr.id as room_id,
    dr.room_number,
    dr.room_label,
    dr.capacity as room_capacity,
    dr.current_occupancy as room_occupancy,
    sd.bed_number,
    sd.assigned_at,
    sd.status as assignment_status
FROM student_dormitory sd
JOIN students s ON sd.student_id = s.id
JOIN dormitories d ON sd.dormitory_id = d.id
JOIN dormitory_rooms dr ON sd.room_id = dr.id
$where_clause
ORDER BY d.dorm_type, d.dorm_name, dr.room_number, s.first_name, s.last_name";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$assignments = mysqli_fetch_all($result, MYSQLI_ASSOC);
$total_students = count($assignments);

// Get statistics
$stats_sql = "SELECT 
    d.dorm_type,
    COUNT(DISTINCT s.id) as total_students,
    COUNT(DISTINCT d.id) as total_dormitories,
    COUNT(DISTINCT dr.id) as total_rooms,
    SUM(dr.capacity) as total_capacity,
    SUM(dr.current_occupancy) as total_occupancy,
    GROUP_CONCAT(DISTINCT d.dorm_name ORDER BY d.dorm_name) as dormitory_names
FROM student_dormitory sd
JOIN students s ON sd.student_id = s.id
JOIN dormitories d ON sd.dormitory_id = d.id
JOIN dormitory_rooms dr ON sd.room_id = dr.id
WHERE sd.status = 'Active' AND s.is_leaver = FALSE";

if ($filter_gender != 'All') {
    $stats_sql .= " AND s.sex = '" . mysqli_real_escape_string($conn, $filter_gender) . "'";
}

$stats_sql .= " GROUP BY d.dorm_type WITH ROLLUP";

$stats_result = mysqli_query($conn, $stats_sql);
$statistics = [];
$overall_stats = [];

while ($row = mysqli_fetch_assoc($stats_result)) {
    if ($row['dorm_type'] === null) {
        $overall_stats = $row;
    } else {
        $statistics[$row['dorm_type']] = $row;
    }
}

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once('../tcpdf/tcpdf.php');
    
    class DormitoryPDF extends TCPDF {
        // Page header
        public function Header() {
            // Logo
            $logo_path = '../muyovozi.png';
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 10, 10, 20, 20, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
                $this->SetFont('helvetica', 'B', 16);
                $this->Cell(0, 0, 'MUYOVOZI HIGH SCHOOL', 0, 1, 'C');
                $this->SetFont('helvetica', '', 10);
                $this->Cell(0, 0, 'Dormitory Students Report', 0, 1, 'C');
                $this->SetY(35);
            } else {
                $this->SetFont('helvetica', 'B', 16);
                $this->Cell(0, 0, 'MUYOVOZI HIGH SCHOOL', 0, 1, 'C');
                $this->SetFont('helvetica', '', 10);
                $this->Cell(0, 0, 'Dormitory Students Report', 0, 1, 'C');
                $this->SetY(30);
            }
            $this->Line(10, $this->GetY() + 0.05, 200, $this->GetY() + 0.05);
            $this->Ln(10);
        }
        
        // Page footer
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }
    
    // Create new PDF document
    $pdf = new DormitoryPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Muyovozi High School');
    $pdf->SetAuthor('Administrator');
    $pdf->SetTitle('Dormitory Students Report');
    $pdf->SetSubject('Dormitory Students List');
    $pdf->SetKeywords('Dormitory, Students, Report, Muyovozi');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'MUYOVOZI HIGH SCHOOL', 'Dormitory Students Report');
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins
    $pdf->SetMargins(10, 35, 10);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Add a page
    $pdf->AddPage();
    
    // Report title
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'DORMITORY STUDENTS DETAILS', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Filter summary
    $pdf->SetFont('helvetica', '', 10);
    $filter_text = "Filters: ";
    $filter_text .= "Gender: " . ($filter_gender != 'All' ? $filter_gender : 'All') . " | ";
    if ($filter_dormitory != 'All') {
        $dorm_name = '';
        foreach ($dormitories as $dorm) {
            if ($dorm['id'] == $filter_dormitory) {
                $dorm_name = $dorm['dorm_name'];
                break;
            }
        }
        $filter_text .= "Dormitory: $dorm_name | ";
    } else {
        $filter_text .= "Dormitory: All | ";
    }
    $filter_text .= "Class: " . ($filter_class != 'All' ? $filter_class : 'All') . " | ";
    $filter_text .= "Combination: " . ($filter_combination != 'All' ? $filter_combination : 'All') . " | ";
    $filter_text .= "Status: $filter_status | ";
    $filter_text .= "Total Students: $total_students";
    $pdf->Cell(0, 10, $filter_text, 0, 1);
    $pdf->Ln(3);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 8);
    $header = array('S/N');
    
    // Dynamically add columns based on inclusion options
    $col_widths = [10];
    $column_count = 1;
    
    $header[] = 'Full Name';
    $col_widths[] = 40;
    $column_count++;
    
    if ($include_index) {
        $header[] = 'Index No.';
        $col_widths[] = 25;
        $column_count++;
    }
    
    if ($include_combination) {
        $header[] = 'Comb.';
        $col_widths[] = 15;
        $column_count++;
    }
    
    if ($include_class) {
        $header[] = 'Class';
        $col_widths[] = 15;
        $column_count++;
    }
    
    if ($include_gender) {
        $header[] = 'Gender';
        $col_widths[] = 15;
        $column_count++;
    }
    
    if ($include_dormitory) {
        $header[] = 'Dormitory';
        $col_widths[] = 25;
        $column_count++;
    }
    
    if ($include_room) {
        $header[] = 'Room';
        $col_widths[] = 15;
        $column_count++;
    }
    
    if ($include_bed) {
        $header[] = 'Bed';
        $col_widths[] = 15;
        $column_count++;
    }
    
    if ($include_status) {
        $header[] = 'Status';
        $col_widths[] = 15;
        $column_count++;
    }
    
    if ($include_date) {
        $header[] = 'Assigned Date';
        $col_widths[] = 20;
        $column_count++;
    }
    
    // Set fill color
    $pdf->SetFillColor(59, 157, 179);
    $pdf->SetTextColor(255);
    $pdf->SetDrawColor(59, 157, 179);
    $pdf->SetLineWidth(0.3);
    
    // Header
    for($i = 0; $i < count($header); $i++) {
        $pdf->Cell($col_widths[$i], 8, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Reset text color
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', '', 8);
    
    // Table content
    $fill = false;
    $sn = 1;
    $current_dorm = '';
    $current_room = '';
    
    foreach($assignments as $student) {
        // Alternate row background
        if($fill) {
            $pdf->SetFillColor(240, 248, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell($col_widths[0], 8, $sn, 1, 0, 'C', $fill);
        $pdf->Cell($col_widths[1], 8, $student['first_name'] . ' ' . $student['last_name'], 1, 0, 'L', $fill);
        
        $col_index = 2;
        
        if ($include_index) {
            $pdf->Cell($col_widths[$col_index], 8, $student['index_number'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_combination) {
            $pdf->Cell($col_widths[$col_index], 8, $student['combination'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_class) {
            $pdf->Cell($col_widths[$col_index], 8, $student['class'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_gender) {
            $pdf->Cell($col_widths[$col_index], 8, $student['sex'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_dormitory) {
            $pdf->Cell($col_widths[$col_index], 8, $student['dorm_name'], 1, 0, 'L', $fill);
            $col_index++;
        }
        
        if ($include_room) {
            $pdf->Cell($col_widths[$col_index], 8, $student['room_number'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_bed) {
            $pdf->Cell($col_widths[$col_index], 8, $student['bed_number'] ?? '-', 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_status) {
            $pdf->Cell($col_widths[$col_index], 8, $student['student_status'] ? 'Active' : 'Inactive', 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_date) {
            $pdf->Cell($col_widths[$col_index], 8, date('Y-m-d', strtotime($student['assigned_at'])), 1, 0, 'C', $fill);
            $col_index++;
        }
        
        $pdf->Ln();
        $fill = !$fill;
        $sn++;
    }
    
    // Add summary section
    if (count($statistics) > 0) {
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 10, 'DORMITORY STATISTICS', 0, 1);
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetFillColor(59, 157, 179, 10);
        
        $stats_cols = ['Dorm Type', 'Dormitories', 'Students', 'Capacity', 'Occupancy', 'Available'];
        $stats_widths = [25, 25, 25, 25, 25, 25];
        
        // Statistics header
        $pdf->SetFillColor(59, 157, 179);
        $pdf->SetTextColor(255);
        for($i = 0; $i < count($stats_cols); $i++) {
            $pdf->Cell($stats_widths[$i], 8, $stats_cols[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();
        
        // Statistics data
        $pdf->SetTextColor(0);
        $stats_fill = false;
        
        foreach($statistics as $dorm_type => $stats) {
            if($stats_fill) {
                $pdf->SetFillColor(240, 248, 250);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $available = $stats['total_capacity'] - $stats['total_occupancy'];
            
            $pdf->Cell($stats_widths[0], 8, $dorm_type, 1, 0, 'C', $stats_fill);
            $pdf->Cell($stats_widths[1], 8, $stats['total_dormitories'], 1, 0, 'C', $stats_fill);
            $pdf->Cell($stats_widths[2], 8, $stats['total_students'], 1, 0, 'C', $stats_fill);
            $pdf->Cell($stats_widths[3], 8, $stats['total_capacity'], 1, 0, 'C', $stats_fill);
            $pdf->Cell($stats_widths[4], 8, $stats['total_occupancy'], 1, 0, 'C', $stats_fill);
            $pdf->Cell($stats_widths[5], 8, $available, 1, 0, 'C', $stats_fill);
            $pdf->Ln();
            $stats_fill = !$stats_fill;
        }
        
        // Overall stats
        if (!empty($overall_stats)) {
            $pdf->SetFillColor(220, 220, 220);
            $pdf->SetFont('helvetica', 'B', 9);
            
            $available = $overall_stats['total_capacity'] - $overall_stats['total_occupancy'];
            
            $pdf->Cell($stats_widths[0], 8, 'TOTAL', 1, 0, 'C', 1);
            $pdf->Cell($stats_widths[1], 8, $overall_stats['total_dormitories'], 1, 0, 'C', 1);
            $pdf->Cell($stats_widths[2], 8, $overall_stats['total_students'], 1, 0, 'C', 1);
            $pdf->Cell($stats_widths[3], 8, $overall_stats['total_capacity'], 1, 0, 'C', 1);
            $pdf->Cell($stats_widths[4], 8, $overall_stats['total_occupancy'], 1, 0, 'C', 1);
            $pdf->Cell($stats_widths[5], 8, $available, 1, 0, 'C', 1);
            $pdf->Ln();
        }
    }
    
    // Output PDF
    $filename = 'dormitory_report_' . date('Y-m-d') . '_' . strtolower($filter_gender) . '.pdf';
    $pdf->Output($filename, 'D');
    exit();
}

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="dormitory_report_' . date('Y-m-d') . '.xls"');
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
            .filter-info { background-color: #f0f0f0; padding: 10px; margin-bottom: 20px; }
        </style>
    </head>
    <body>';
    
    echo '<div class="header">MUYOVOZI HIGH SCHOOL - DORMITORY STUDENTS REPORT</div>';
    
    // Filter info
    echo '<div class="filter-info">';
    echo '<strong>Filters Applied:</strong><br>';
    echo 'Gender: ' . ($filter_gender != 'All' ? $filter_gender : 'All') . ' | ';
    echo 'Status: ' . $filter_status . ' | ';
    echo 'Class: ' . ($filter_class != 'All' ? $filter_class : 'All') . ' | ';
    echo 'Combination: ' . ($filter_combination != 'All' ? $filter_combination : 'All') . ' | ';
    echo 'Total Students: ' . $total_students;
    echo '</div>';
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>S/N</th>';
    echo '<th>Full Name</th>';
    
    if ($include_index) echo '<th>Index No.</th>';
    if ($include_combination) echo '<th>Combination</th>';
    if ($include_class) echo '<th>Class</th>';
    if ($include_gender) echo '<th>Gender</th>';
    if ($include_dormitory) echo '<th>Dormitory</th>';
    if ($include_room) echo '<th>Room</th>';
    if ($include_bed) echo '<th>Bed No.</th>';
    if ($include_status) echo '<th>Status</th>';
    if ($include_date) echo '<th>Assigned Date</th>';
    
    echo '</tr>';
    
    $sn = 1;
    foreach($assignments as $student) {
        echo '<tr>';
        echo '<td>' . $sn . '</td>';
        echo '<td>' . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . '</td>';
        
        if ($include_index) {
            echo '<td>' . htmlspecialchars($student['index_number']) . '</td>';
        }
        
        if ($include_combination) {
            echo '<td>' . htmlspecialchars($student['combination']) . '</td>';
        }
        
        if ($include_class) {
            echo '<td>' . htmlspecialchars($student['class']) . '</td>';
        }
        
        if ($include_gender) {
            echo '<td>' . htmlspecialchars($student['sex']) . '</td>';
        }
        
        if ($include_dormitory) {
            echo '<td>' . htmlspecialchars($student['dorm_name']) . '</td>';
        }
        
        if ($include_room) {
            echo '<td>' . htmlspecialchars($student['room_number']) . '</td>';
        }
        
        if ($include_bed) {
            echo '<td>' . htmlspecialchars($student['bed_number'] ?? '-') . '</td>';
        }
        
        if ($include_status) {
            echo '<td>' . ($student['student_status'] ? 'Active' : 'Inactive') . '</td>';
        }
        
        if ($include_date) {
            echo '<td>' . date('Y-m-d', strtotime($student['assigned_at'])) . '</td>';
        }
        
        echo '</tr>';
        $sn++;
    }
    
    echo '</table>';
    
    // Add statistics
    if (count($statistics) > 0) {
        echo '<br><br><h3>Statistics Summary</h3>';
        echo '<table border="1">';
        echo '<tr><th>Dorm Type</th><th>Dormitories</th><th>Students</th><th>Capacity</th><th>Occupancy</th><th>Available</th></tr>';
        
        foreach($statistics as $dorm_type => $stats) {
            $available = $stats['total_capacity'] - $stats['total_occupancy'];
            echo '<tr>';
            echo '<td>' . $dorm_type . '</td>';
            echo '<td>' . $stats['total_dormitories'] . '</td>';
            echo '<td>' . $stats['total_students'] . '</td>';
            echo '<td>' . $stats['total_capacity'] . '</td>';
            echo '<td>' . $stats['total_occupancy'] . '</td>';
            echo '<td>' . $available . '</td>';
            echo '</tr>';
        }
        
        if (!empty($overall_stats)) {
            $available = $overall_stats['total_capacity'] - $overall_stats['total_occupancy'];
            echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
            echo '<td>TOTAL</td>';
            echo '<td>' . $overall_stats['total_dormitories'] . '</td>';
            echo '<td>' . $overall_stats['total_students'] . '</td>';
            echo '<td>' . $overall_stats['total_capacity'] . '</td>';
            echo '<td>' . $overall_stats['total_occupancy'] . '</td>';
            echo '<td>' . $available . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }
    
    echo '</body></html>';
    exit();
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">Advanced Dormitory Reports</h2>
            <div>
                <div class="dropdown d-md-block d-none">
                <button class="btn btn-primary dropdown-toggle" type="button" id="actionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog me-2"></i>Actions
                </button>
                <ul class="dropdown-menu" aria-labelledby="actionsDropdown">
                   <li><a class="dropdown-item" href="dormitory.php"><i class="fas fa-bed"></i>Manage Dorms
                    </a>
                </li>
                    <li><a class="dropdown-item" href="male.php">
                        <i class="fas fa-male"></i>
                         Male Dorms 
                    </a>
                </li>
                <li>
                   <li><a class="dropdown-item" href="female.php">
                        <i class="fas fa-female"></i>
                         Female Dorms 
                    </a>
                </li>
                </ul>
            </div>
            <!-- Mobile Actions Button -->
            <div class="dropdown d-md-none">
                <button class="btn btn-primary" type="button" id="mobileActionsBtn" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="mobileActionsBtn">

                  <li>
                    <li><a class="dropdown-item"  href="dormitory.php">
                        <i class="fas fa-bed"></i>
                         Manage Dorms 
                    </a>
                </li>
                <li>
                    <li><a class="dropdown-item"  href="male.php">
                        <i class="fas fa-male"></i>
                         Male Dorms 
                    </a>
                </li>
                <li>
                    <li><a class="dropdown-item"  href="female.php">
                        <i class="fas fa-female"></i>
                         Female Dorms 
                    </a>
                </li>
                </ul>
            </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card mb-4">
            <div class="card-header" style=" background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);">
                <h4 class="mb-0">
                    <i class="fas fa-filter me-2"></i>Filter Options
                </h4>
            </div>
            <div class="card-body">
                <form method="GET" action="reports.php" id="filterForm">
                    <div class="row">
                        <!-- Gender Filter -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select" id="genderFilter">
                                <option value="All" <?php echo $filter_gender == 'All' ? 'selected' : ''; ?>>All Genders</option>
                                <option value="Male" <?php echo $filter_gender == 'Male' ? 'selected' : ''; ?>>Male Only</option>
                                <option value="Female" <?php echo $filter_gender == 'Female' ? 'selected' : ''; ?>>Female Only</option>
                            </select>
                        </div>
                        
                        <!-- Dormitory Filter -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Dormitory</label>
                            <select name="dormitory" class="form-select" id="dormitoryFilter">
                                <option value="All" <?php echo $filter_dormitory == 'All' ? 'selected' : ''; ?>>All Dormitories</option>
                                <?php foreach ($dormitories as $dorm): ?>
                                <option value="<?php echo $dorm['id']; ?>" 
                                    <?php echo $filter_dormitory == $dorm['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dorm['dorm_name']); ?> (<?php echo $dorm['dorm_type']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Class Filter -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Class</label>
                            <select name="class" class="form-select" id="classFilter">
                                <option value="All" <?php echo $filter_class == 'All' ? 'selected' : ''; ?>>All Classes</option>
                                <option value="Form Five" <?php echo $filter_class == 'Form Five' ? 'selected' : ''; ?>>Form Five</option>
                                <option value="Form Six" <?php echo $filter_class == 'Form Six' ? 'selected' : ''; ?>>Form Six</option>
                            </select>
                        </div>
                        
                        <!-- Combination Filter -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Combination</label>
                            <select name="combination" class="form-select" id="combinationFilter">
                                <option value="All" <?php echo $filter_combination == 'All' ? 'selected' : ''; ?>>All Combinations</option>
                                <?php foreach ($combinations as $combo): ?>
                                <option value="<?php echo $combo; ?>" <?php echo $filter_combination == $combo ? 'selected' : ''; ?>>
                                    <?php echo $combo; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Status Filter -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Student Status</label>
                            <select name="status" class="form-select" id="statusFilter">
                                <option value="Active" <?php echo $filter_status == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $filter_status == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="All" <?php echo $filter_status == 'All' ? 'selected' : ''; ?>>All</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Column Selection -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h6 class="mb-3">Select Columns to Include in Report:</h6>
                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_index" value="1" 
                                               id="includeIndex" <?php echo $include_index ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeIndex">
                                            Index Number
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_combination" value="1" 
                                               id="includeCombination" <?php echo $include_combination ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeCombination">
                                            Combination
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_class" value="1" 
                                               id="includeClass" <?php echo $include_class ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeClass">
                                            Class
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_gender" value="1" 
                                               id="includeGender" <?php echo $include_gender ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeGender">
                                            Gender
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_dormitory" value="1" 
                                               id="includeDormitory" <?php echo $include_dormitory ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeDormitory">
                                            Dormitory
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_room" value="1" 
                                               id="includeRoom" <?php echo $include_room ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeRoom">
                                            Room
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_bed" value="1" 
                                               id="includeBed" <?php echo $include_bed ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeBed">
                                            Bed Number
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_status" value="1" 
                                               id="includeStatus" <?php echo $include_status ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeStatus">
                                            Student Status
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_date" value="1" 
                                               id="includeDate" <?php echo $include_date ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeDate">
                                            Assigned Date
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-info">Found: <?php echo $total_students; ?> students 
                                    <?php if (!empty($overall_stats)): ?>
                                    <span class="badge bg-secondary ms-2">
                                        Capacity: <?php echo $overall_stats['total_capacity']; ?>
                                     
                                    <span class="badge bg-success ms-2">
                                        Occupied: <?php echo $overall_stats['total_occupancy']; ?>
                                     
                                    <span class="badge bg-warning ms-2">
                                        Available: <?php echo $overall_stats['total_capacity'] - $overall_stats['total_occupancy']; ?>
                                     
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search me-2"></i>Apply Filters
                                    </button>
                                    <a href="reports.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-redo me-2"></i>Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Summary -->
        <?php if (count($statistics) > 0): ?>
        <div class="row mb-4">
            <?php foreach ($statistics as $dorm_type => $stats): 
                $available = $stats['total_capacity'] - $stats['total_occupancy'];
                $occupancy_rate = $stats['total_capacity'] > 0 ? round(($stats['total_occupancy'] / $stats['total_capacity']) * 100, 1) : 0;
            ?>
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header" style="background-color: <?php echo $dorm_type == 'Male' ? '#007bff' : '#e83e8c'; ?>; color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-<?php echo $dorm_type == 'Male' ? 'male' : 'female'; ?> me-2"></i>
                            <?php echo $dorm_type; ?> Dormitories
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <p class="mb-1"><small>Dormitories:</small></p>
                                <h4><?php echo $stats['total_dormitories']; ?></h4>
                            </div>
                            <div class="col-6">
                                <p class="mb-1"><small>Students:</small></p>
                                <h4><?php echo $stats['total_students']; ?></h4>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <p class="mb-1"><small>Capacity:</small></p>
                                <h5><?php echo $stats['total_capacity']; ?></h5>
                            </div>
                            <div class="col-6">
                                <p class="mb-1"><small>Occupied:</small></p>
                                <h5><?php echo $stats['total_occupancy']; ?></h5>
                            </div>
                        </div>
                        <div class="progress mt-2">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?php echo $occupancy_rate; ?>%; background-color: <?php echo $dorm_type == 'Male' ? '#007bff' : '#e83e8c'; ?>;">
                                <?php echo $occupancy_rate; ?>%
                            </div>
                        </div>
                        <p class="mt-2 mb-0"><small>Available Beds: <?php echo $available; ?></small></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (!empty($overall_stats)): 
                $available = $overall_stats['total_capacity'] - $overall_stats['total_occupancy'];
                $occupancy_rate = $overall_stats['total_capacity'] > 0 ? round(($overall_stats['total_occupancy'] / $overall_stats['total_capacity']) * 100, 1) : 0;
            ?>
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header" style=" background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Overall Summary
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <p class="mb-1"><small>Total Dormitories:</small></p>
                                <h4><?php echo $overall_stats['total_dormitories']; ?></h4>
                            </div>
                            <div class="col-6">
                                <p class="mb-1"><small>Total Students:</small></p>
                                <h4><?php echo $overall_stats['total_students']; ?></h4>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <p class="mb-1"><small>Total Capacity:</small></p>
                                <h5><?php echo $overall_stats['total_capacity']; ?></h5>
                            </div>
                            <div class="col-6">
                                <p class="mb-1"><small>Total Occupied:</small></p>
                                <h5><?php echo $overall_stats['total_occupancy']; ?></h5>
                            </div>
                        </div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $occupancy_rate; ?>%;">
                                <?php echo $occupancy_rate; ?>%
                            </div>
                        </div>
                        <p class="mt-2 mb-0"><small>Total Available Beds: <?php echo $available; ?></small></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Export Options -->
        <div class="card mb-4">
            <div class="card-header" style=" background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);">
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
                            <p class="text-muted">Professional PDF report with logo and statistics</p>
                            <?php
                            $pdf_url = "reports.php?" . http_build_query([
                                'gender' => $filter_gender,
                                'dormitory' => $filter_dormitory,
                                'class' => $filter_class,
                                'combination' => $filter_combination,
                                'status' => $filter_status,
                                'include_index' => $include_index ? 1 : 0,
                                'include_combination' => $include_combination ? 1 : 0,
                                'include_class' => $include_class ? 1 : 0,
                                'include_gender' => $include_gender ? 1 : 0,
                                'include_dormitory' => $include_dormitory ? 1 : 0,
                                'include_room' => $include_room ? 1 : 0,
                                'include_bed' => $include_bed ? 1 : 0,
                                'include_status' => $include_status ? 1 : 0,
                                'include_date' => $include_date ? 1 : 0,
                                'export' => 'pdf'
                            ]);
                            ?>
                            <a href="<?php echo $pdf_url; ?>" class="btn btn-danger btn-lg">
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
                            $excel_url = "reports.php?" . http_build_query([
                                'gender' => $filter_gender,
                                'dormitory' => $filter_dormitory,
                                'class' => $filter_class,
                                'combination' => $filter_combination,
                                'status' => $filter_status,
                                'include_index' => $include_index ? 1 : 0,
                                'include_combination' => $include_combination ? 1 : 0,
                                'include_class' => $include_class ? 1 : 0,
                                'include_gender' => $include_gender ? 1 : 0,
                                'include_dormitory' => $include_dormitory ? 1 : 0,
                                'include_room' => $include_room ? 1 : 0,
                                'include_bed' => $include_bed ? 1 : 0,
                                'include_status' => $include_status ? 1 : 0,
                                'include_date' => $include_date ? 1 : 0,
                                'export' => 'excel'
                            ]);
                            ?>
                            <a href="<?php echo $excel_url; ?>" class="btn btn-success btn-lg">
                                <i class="fas fa-download me-2"></i>Download Excel
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Report Features:</strong> 
                    <ul class="mb-0">
                        <li>PDF report includes school logo and professional formatting</li>
                        <li>Excel export is suitable for further data analysis</li>
                        <li>Statistics section shows occupancy rates and available beds</li>
                        <li>Only selected columns will be included in the export</li>
                        <li>Reports can be filtered by gender, dormitory, class, and combination</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Preview Table -->
        <div class="card">
            <div class="card-header" style=" background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-eye me-2"></i>Report Preview
                    </h4>
                    <div>
                        <span class="badge bg-light text-dark">
                            <?php echo $total_students; ?> students
                         
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($total_students > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="previewTable">
                        <thead class="table-light">
                            <tr>
                                <th>S/N</th>
                                <th>Full Name</th>
                                <?php if ($include_index): ?>
                                <th>Index No.</th>
                                <?php endif; ?>
                                <?php if ($include_combination): ?>
                                <th>Combination</th>
                                <?php endif; ?>
                                <?php if ($include_class): ?>
                                <th>Class</th>
                                <?php endif; ?>
                                <?php if ($include_gender): ?>
                                <th>Gender</th>
                                <?php endif; ?>
                                <?php if ($include_dormitory): ?>
                                <th>Dormitory</th>
                                <?php endif; ?>
                                <?php if ($include_room): ?>
                                <th>Room</th>
                                <?php endif; ?>
                                <?php if ($include_bed): ?>
                                <th>Bed No.</th>
                                <?php endif; ?>
                                <?php if ($include_status): ?>
                                <th>Status</th>
                                <?php endif; ?>
                                <?php if ($include_date): ?>
                                <th>Assigned Date</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($assignments as $index => $student): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3">
                                            <?php if ($student['sex'] == 'Male'): ?>
                                                <i class="fas fa-male text-primary"></i>
                                            <?php else: ?>
                                                <i class="fas fa-female" style="color: #e83e8c;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                            <?php if (!empty($student['second_name'])): ?>
                                                <div class="small text-muted">
                                                    <?php echo htmlspecialchars($student['second_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <?php if ($include_index): ?>
                                <td>
                                    <strong><?php echo htmlspecialchars($student['index_number']); ?></strong>
                                </td>
                                <?php endif; ?>
                                <?php if ($include_combination): ?>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($student['combination']); ?> 
                                </td>
                                <?php endif; ?>
                                <?php if ($include_class): ?>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($student['class']); ?> 
                                </td>
                                <?php endif; ?>
                                <?php if ($include_gender): ?>
                                <td>
                                    <span class="badge <?php echo $student['sex'] == 'Male' ? 'bg-info' : 'bg-pink'; ?>">
                                        <?php echo htmlspecialchars($student['sex']); ?>
                                     
                                </td>
                                <?php endif; ?>
                                <?php if ($include_dormitory): ?>
                                <td>
                                    <span class="badge" style="background-color: <?php echo $student['dorm_type'] == 'Male' ? '#007bff' : '#e83e8c'; ?>; color: white;">
                                        <?php echo htmlspecialchars($student['dorm_name']); ?>
                                     
                                </td>
                                <?php endif; ?>
                                <?php if ($include_room): ?>
                                <td>
                                    <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($student['room_number']); ?> 
                                    <?php if (!empty($student['room_label'])): ?>
                                        <div class="small text-muted"><?php echo htmlspecialchars($student['room_label']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <?php if ($include_bed): ?>
                                <td>
                                    <?php if (!empty($student['bed_number'])): ?>
                                        <span class="badge bg-dark"><?php echo htmlspecialchars($student['bed_number']); ?> 
                                    <?php else: ?>
                                        <span class="text-muted">- 
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <?php if ($include_status): ?>
                                <td>
                                    <span class="badge <?php echo $student['student_status'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $student['student_status'] ? 'Active' : 'Inactive'; ?>
                                     
                                </td>
                                <?php endif; ?>
                                <?php if ($include_date): ?>
                                <td><?php echo date('Y-m-d', strtotime($student['assigned_at'])); ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-bed fa-3x text-muted mb-3"></i>
                    <h4>No dormitory assignments found</h4>
                    <p class="text-muted">Try adjusting your filter criteria</p>
                    <a href="reports.php" class="btn btn-primary">
                        <i class="fas fa-redo me-2"></i>Reset Filters
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize form controls
document.addEventListener('DOMContentLoaded', function() {
    // Update all switch labels
    const checkboxes = document.querySelectorAll('.form-check-input');
    checkboxes.forEach(cb => {
        const label = cb.nextElementSibling;
        cb.addEventListener('change', function() {
            // Update label if needed
        });
    });
    
    // Apply filters on change
    const filters = ['genderFilter', 'dormitoryFilter', 'classFilter', 'combinationFilter', 'statusFilter'];
    filters.forEach(filterId => {
        const element = document.getElementById(filterId);
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
        const checkboxes = document.querySelectorAll('.form-check-input');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
        });
        
        this.innerHTML = allChecked ? 
            '<i class="fas fa-toggle-on me-1"></i>Select All Columns' : 
            '<i class="fas fa-toggle-off me-1"></i>Deselect All Columns';
    });
    
    const columnOptionsDiv = document.querySelector('.row.mb-3 .col-md-12');
    if (columnOptionsDiv) {
        columnOptionsDiv.insertBefore(toggleAllBtn, columnOptionsDiv.firstChild);
    }
});
</script>

<style>
/* Custom styles for report page */
.export-option {
    transition: transform 0.3s ease;
}

.export-option:hover {
    transform: translateY(-5px);
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

.table th {
    background-color: rgba(59, 157, 179, 0.1);
    border-bottom: 2px solid #3B9DB3;
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(59, 157, 179, 0.02);
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
    font-size: 1.8rem;
    margin-bottom: 10px;
}

.stats-card.simple-card h3 {
    font-size: 1.5rem;
    font-weight: bold;
    margin: 10px 0 5px 0;
}

.stats-card.simple-card p {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .stats-card.simple-card {
        padding: 15px;
        margin-bottom: 10px;
    }
    
    .stats-card.simple-card h3 {
        font-size: 1.3rem;
    }
    
    .btn-lg {
        padding: 10px 20px;
        font-size: 1rem;
    }
    
    .export-option {
        margin-bottom: 15px;
    }
    
    .table-responsive {
        font-size: 0.9rem;
    }
    
    .avatar-circle {
        width: 30px;
        height: 30px;
        margin-right: 8px;
    }
}
</style>

<?php include '../controller/footer.php'; ?>
