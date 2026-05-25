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
    if ($role_id == 1 || $role_id == 2 || $role_id == 3) { // Head Master or Second Master
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view page you need.";
    header("Location: ../404.php");
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

// Set timezone
date_default_timezone_set('Africa/Dar_es_Salaam');

// Default values
$filter_type = $_GET['type'] ?? '';
$filter_year = $_GET['year'] ?? '';
$filter_class = $_GET['class'] ?? '';
$filter_combination = $_GET['combination'] ?? '';
$filter_gender = $_GET['gender'] ?? '';
$include_gender = isset($_GET['include_gender']) && $_GET['include_gender'] == 1 ? true : false;
$include_class = isset($_GET['include_class']) && $_GET['include_class'] == 1 ? true : false;
$include_admission = isset($_GET['include_admission']) && $_GET['include_admission'] == 1 ? true : false;
$include_combination = isset($_GET['include_combination']) && $_GET['include_combination'] == 1 ? true : false;
$include_dob = isset($_GET['include_dob']) && $_GET['include_dob'] == 1 ? true : false;
$include_reason = isset($_GET['include_reason']) && $_GET['include_reason'] == 1 ? true : false;
$include_date = isset($_GET['include_date']) && $_GET['include_date'] == 1 ? true : false;

// Build SQL query based on filters
$where_conditions = ["s.is_leaver = TRUE"];
$params = [];

if (!empty($filter_type)) {
    if ($filter_type == 'Graduated') {
        $where_conditions[] = "s.graduation_status = 'Graduated'";
    } else if ($filter_type == 'Left') {
        $where_conditions[] = "s.graduation_status != 'Graduated'";
    }
}

if (!empty($filter_year)) {
    $where_conditions[] = "s.year_left = ?";
    $params[] = $filter_year;
}

if (!empty($filter_class)) {
    $where_conditions[] = "s.previous_class = ?";
    $params[] = $filter_class;
}

if (!empty($filter_combination)) {
    $where_conditions[] = "s.combination = ?";
    $params[] = $filter_combination;
}

if (!empty($filter_gender)) {
    $where_conditions[] = "s.sex = ?";
    $params[] = $filter_gender;
}

$where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Order by year left and index number
$order_by = "ORDER BY s.year_left DESC, s.index_number ASC";

// Get filtered leavers with leaver details
$sql = "SELECT s.*, sl.reason, sl.leaver_type, sl.left_at, sl.returned
        FROM students s
        LEFT JOIN student_leavers sl ON s.id = sl.student_id
        $where_clause $order_by";
        
$stmt = mysqli_prepare($conn, $sql);

if (!empty($params)) {
    $types = str_repeat('s', count($params));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$leavers = mysqli_fetch_all($result, MYSQLI_ASSOC);
$total_leavers = count($leavers);

// Get statistics for report
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN s.graduation_status = 'Graduated' THEN 1 ELSE 0 END) as graduates,
    SUM(CASE WHEN s.graduation_status != 'Graduated' THEN 1 ELSE 0 END) as regular_leavers,
    SUM(CASE WHEN s.sex = 'Male' THEN 1 ELSE 0 END) as males,
    SUM(CASE WHEN s.sex = 'Female' THEN 1 ELSE 0 END) as females,
    SUM(CASE WHEN s.previous_class = 'Form Five' THEN 1 ELSE 0 END) as form_five,
    SUM(CASE WHEN s.previous_class = 'Form Six' THEN 1 ELSE 0 END) as form_six,
    SUM(CASE WHEN s.year_left = YEAR(CURDATE()) THEN 1 ELSE 0 END) as this_year,
    GROUP_CONCAT(DISTINCT s.year_left ORDER BY s.year_left DESC) as all_years
    FROM students s
    WHERE s.is_leaver = TRUE";

$stats_result = mysqli_query($conn, $stats_sql);
$overall_stats = mysqli_fetch_assoc($stats_result);

// Get combination statistics for leavers
$comb_stats_sql = "SELECT s.combination, 
                   COUNT(*) as total,
                   SUM(CASE WHEN s.sex = 'Male' THEN 1 ELSE 0 END) as males,
                   SUM(CASE WHEN s.sex = 'Female' THEN 1 ELSE 0 END) as females
                   FROM students s
                   WHERE s.is_leaver = TRUE
                   GROUP BY s.combination
                   ORDER BY FIELD(s.combination, 'HGE', 'HGL', 'HGK', 'HKL', 'KLF', 'EGM', 'HLF', 'HGF')";
$comb_stats_result = mysqli_query($conn, $comb_stats_sql);
$combination_stats = [];
while ($row = mysqli_fetch_assoc($comb_stats_result)) {
    $combination_stats[$row['combination']] = $row;
}

// Handle PDF generation
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once('../tcpdf/tcpdf.php');
    
    class LeaverPDF extends TCPDF {
        // Page header
        public function Header() {
            // Logo
            $logo_path = '../muyovozi.png';
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 10, 10, 20, 20, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
                $this->SetFont('helvetica', 'B', 16);
                $this->Cell(0, 0, 'MUYOVOZI HIGH SCHOOL', 0, 1, 'C');
                $this->SetFont('helvetica', '', 10);
                $this->Cell(0, 0, 'Leavers & Graduates Report', 0, 1, 'C');
                $this->SetY(35);
            } else {
                $this->SetFont('helvetica', 'B', 16);
                $this->Cell(0, 0, 'MUYOVOZI HIGH SCHOOL', 0, 1, 'C');
                $this->SetFont('helvetica', '', 10);
                $this->Cell(0, 0, 'Leavers & Graduates Report', 0, 1, 'C');
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
    $pdf = new LeaverPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Muyovozi High School');
    $pdf->SetAuthor('Administrator');
    $pdf->SetTitle('Leavers Report');
    $pdf->SetSubject('Leavers & Graduates List');
    $pdf->SetKeywords('Leavers, Graduates, Report, Muyovozi');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'MUYOVOZI HIGH SCHOOL', 'Leavers & Graduates Report');
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins
    $pdf->SetMargins(15, 35, 15);
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
    $pdf->Cell(0, 10, 'LEAVERS & GRADUATES REPORT', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Filter summary
    $pdf->SetFont('helvetica', '', 10);
    $filter_text = "Filters Applied: ";
    if (!empty($filter_type)) $filter_text .= "Type: $filter_type | ";
    if (!empty($filter_year)) $filter_text .= "Year: $filter_year | ";
    if (!empty($filter_class)) $filter_text .= "Class: $filter_class | ";
    if (!empty($filter_combination)) $filter_text .= "Combination: $filter_combination | ";
    if (!empty($filter_gender)) $filter_text .= "Gender: $filter_gender | ";
    $filter_text .= "Total Records: $total_leavers";
    $pdf->Cell(0, 10, $filter_text, 0, 1);
    $pdf->Ln(3);
    
    // Summary statistics
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 10, 'SUMMARY STATISTICS', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    $summary_data = [
        ['Total Leavers', $overall_stats['total'] ?? 0],
        ['Graduates', $overall_stats['graduates'] ?? 0],
        ['Regular Leavers', $overall_stats['regular_leavers'] ?? 0],
        ['Male', $overall_stats['males'] ?? 0],
        ['Female', $overall_stats['females'] ?? 0],
        ['This Year', $overall_stats['this_year'] ?? 0]
    ];
    
    $pdf->SetFillColor(240, 248, 250);
    $pdf->SetDrawColor(200, 200, 200);
    
    foreach ($summary_data as $row) {
        $pdf->Cell(60, 8, $row[0], 1, 0, 'L', true);
        $pdf->Cell(30, 8, $row[1], 1, 1, 'C', true);
    }
    
    $pdf->Ln(10);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 9);
    $header = array('S/N', 'Index No.', 'Full Name');
    
    // Dynamically add columns based on inclusion options
    $col_widths = [12, 25, 55];
    $column_count = 3;
    
    if ($include_combination) {
        $header[] = 'Comb.';
        $col_widths[] = 15;
        $column_count++;
    }
    
    if ($include_gender) {
        $header[] = 'Gender';
        $col_widths[] = 15;
        $column_count++;
    }
    
    if ($include_class) {
        $header[] = 'Class';
        $col_widths[] = 18;
        $column_count++;
    }
    
    if ($include_dob) {
        $header[] = 'DOB';
        $col_widths[] = 20;
        $column_count++;
    }
    
    if ($include_reason) {
        $header[] = 'Reason';
        $col_widths[] = 25;
        $column_count++;
    }
    
    if ($include_date) {
        $header[] = 'Left Date';
        $col_widths[] = 20;
        $column_count++;
    }
    
    if ($include_admission) {
        $header[] = 'Admission No.';
        $col_widths[] = 25;
        $column_count++;
    }
    
    // Set fill color for header
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
    $pdf->SetFont('helvetica', '', 9);
    
    // Table content
    $fill = false;
    $sn = 1;
    
    foreach($leavers as $leaver) {
        // Alternate row background
        if($fill) {
            $pdf->SetFillColor(240, 248, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell($col_widths[0], 8, $sn, 1, 0, 'C', $fill);
        $pdf->Cell($col_widths[1], 8, $leaver['index_number'], 1, 0, 'C', $fill);
        $pdf->Cell($col_widths[2], 8, $leaver['first_name'] . ' ' . $leaver['last_name'], 1, 0, 'L', $fill);
        
        $col_index = 3;
        
        if ($include_combination) {
            $pdf->Cell($col_widths[$col_index], 8, $leaver['combination'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_gender) {
            $pdf->Cell($col_widths[$col_index], 8, $leaver['sex'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_class) {
            $pdf->Cell($col_widths[$col_index], 8, $leaver['previous_class'] ?: $leaver['class'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_dob) {
            $pdf->Cell($col_widths[$col_index], 8, $leaver['date_of_birth'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_reason) {
            $reason = $leaver['reason'] ?: ($leaver['graduation_status'] == 'Graduated' ? 'Graduated' : 'Not specified');
            $pdf->Cell($col_widths[$col_index], 8, $reason, 1, 0, 'L', $fill);
            $col_index++;
        }
        
        if ($include_date) {
            $left_date = !empty($leaver['left_at']) ? date('d/m/Y', strtotime($leaver['left_at'])) : '';
            $pdf->Cell($col_widths[$col_index], 8, $left_date, 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_admission) {
            $pdf->Cell($col_widths[$col_index], 8, $leaver['admission_number'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        $pdf->Ln();
        $fill = !$fill;
        $sn++;
    }
    
    // Add combination statistics section
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 10, 'COMBINATION STATISTICS FOR LEAVERS', 0, 1);
    
    $pdf->SetFont('helvetica', '', 9);
    
    $comb_cols = ['Combination', 'Total', 'Male', 'Female'];
    $comb_widths = [40, 25, 25, 25];
    
    // Combination header
    $pdf->SetFillColor(59, 157, 179);
    $pdf->SetTextColor(255);
    for($i = 0; $i < count($comb_cols); $i++) {
        $pdf->Cell($comb_widths[$i], 8, $comb_cols[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Combination data
    $pdf->SetTextColor(0);
    $comb_fill = false;
    
    foreach($combination_stats as $comb => $stats) {
        if($comb_fill) {
            $pdf->SetFillColor(240, 248, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell($comb_widths[0], 8, $comb, 1, 0, 'C', $comb_fill);
        $pdf->Cell($comb_widths[1], 8, $stats['total'], 1, 0, 'C', $comb_fill);
        $pdf->Cell($comb_widths[2], 8, $stats['males'], 1, 0, 'C', $comb_fill);
        $pdf->Cell($comb_widths[3], 8, $stats['females'], 1, 0, 'C', $comb_fill);
        $pdf->Ln();
        $comb_fill = !$comb_fill;
    }
    
    // Add year statistics
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 10, 'YEARLY DISTRIBUTION', 0, 1);
    
    $pdf->SetFont('helvetica', '', 9);
    
    // Get year statistics
    $year_stats_sql = "SELECT year_left, COUNT(*) as total 
                      FROM students 
                      WHERE is_leaver = TRUE 
                      GROUP BY year_left 
                      ORDER BY year_left DESC";
    $year_stats_result = mysqli_query($conn, $year_stats_sql);
    
    $year_cols = ['Year', 'Total Leavers'];
    $year_widths = [40, 40];
    
    // Year header
    $pdf->SetFillColor(59, 157, 179);
    $pdf->SetTextColor(255);
    for($i = 0; $i < count($year_cols); $i++) {
        $pdf->Cell($year_widths[$i], 8, $year_cols[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Year data
    $pdf->SetTextColor(0);
    $year_fill = false;
    
    while($year_row = mysqli_fetch_assoc($year_stats_result)) {
        if($year_fill) {
            $pdf->SetFillColor(240, 248, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell($year_widths[0], 8, $year_row['year_left'], 1, 0, 'C', $year_fill);
        $pdf->Cell($year_widths[1], 8, $year_row['total'], 1, 0, 'C', $year_fill);
        $pdf->Ln();
        $year_fill = !$year_fill;
    }
    
    // Output PDF
    $pdf->Output('leavers_report_' . date('Y-m-d') . '.pdf', 'D');
    exit();
}

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="leavers_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    $colspan = 3; // S/N, Index No., Full Name are always included
    if ($include_combination) $colspan++;
    if ($include_gender) $colspan++;
    if ($include_class) $colspan++;
    if ($include_dob) $colspan++;
    if ($include_reason) $colspan++;
    if ($include_date) $colspan++;
    if ($include_admission) $colspan++;
    
    echo '<table border="1">
        <tr><th colspan="' . $colspan . '">MUYOVOZI HIGH SCHOOL - LEAVERS & GRADUATES REPORT</th></tr>
        <tr>
            <th>S/N</th>
            <th>Index No.</th>
            <th>Full Name</th>';
    
    if ($include_combination) {
        echo '<th>Combination</th>';
    }
    
    if ($include_gender) {
        echo '<th>Gender</th>';
    }
    
    if ($include_class) {
        echo '<th>Class</th>';
    }
    
    if ($include_dob) {
        echo '<th>Date of Birth</th>';
    }
    
    if ($include_reason) {
        echo '<th>Reason</th>';
    }
    
    if ($include_date) {
        echo '<th>Left Date</th>';
    }
    
    if ($include_admission) {
        echo '<th>Admission No.</th>';
    }
    
    echo '</tr>';
    
    $sn = 1;
    foreach($leavers as $leaver) {
        echo '<tr>';
        echo '<td>' . $sn . '</td>';
        echo '<td>' . $leaver['index_number'] . '</td>';
        echo '<td>' . $leaver['first_name'] . ' ' . $leaver['last_name'] . '</td>';
        
        if ($include_combination) {
            echo '<td>' . $leaver['combination'] . '</td>';
        }
        
        if ($include_gender) {
            echo '<td>' . $leaver['sex'] . '</td>';
        }
        
        if ($include_class) {
            echo '<td>' . ($leaver['previous_class'] ?: $leaver['class']) . '</td>';
        }
        
        if ($include_dob) {
            echo '<td>' . $leaver['date_of_birth'] . '</td>';
        }
        
        if ($include_reason) {
            $reason = $leaver['reason'] ?: ($leaver['graduation_status'] == 'Graduated' ? 'Graduated' : 'Not specified');
            echo '<td>' . $reason . '</td>';
        }
        
        if ($include_date) {
            $left_date = !empty($leaver['left_at']) ? date('d/m/Y', strtotime($leaver['left_at'])) : '';
            echo '<td>' . $left_date . '</td>';
        }
        
        if ($include_admission) {
            echo '<td>' . $leaver['admission_number'] . '</td>';
        }
        
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
            <h2 class="page-title">Leavers & Graduates Report Generator</h2>
            <div>
                <a href="leavers.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Leavers
                </a>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card mb-4">
            <div class="card-header" style=" background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);">
                <h4 class="mb-0">
                    <i class="fas fa-filter me-2"></i>Filter & Column Options
                </h4>
            </div>
            <div class="card-body">
                <form method="GET" action="report_leaver.php" id="filterForm">
                    <div class="row">
                        <!-- Type Filter -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select" id="typeFilter">
                                <option value="">All Types</option>
                                <option value="Graduated" <?php echo $filter_type == 'Graduated' ? 'selected' : ''; ?>>Graduates</option>
                                <option value="Left" <?php echo $filter_type == 'Left' ? 'selected' : ''; ?>>Regular Leavers</option>
                            </select>
                        </div>
                        
                        <!-- Year Filter -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Year Left</label>
                            <select name="year" class="form-select" id="yearFilter">
                                <option value="">All Years</option>
                                <?php
                                $years = explode(',', $overall_stats['all_years'] ?? '');
                                $years = array_filter(array_unique($years));
                                rsort($years);
                                foreach($years as $year):
                                ?>
                                <option value="<?php echo $year; ?>" <?php echo $filter_year == $year ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Class Filter -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Class</label>
                            <select name="class" class="form-select" id="classFilter">
                                <option value="">All Classes</option>
                                <option value="Form Five" <?php echo $filter_class == 'Form Five' ? 'selected' : ''; ?>>Form Five</option>
                                <option value="Form Six" <?php echo $filter_class == 'Form Six' ? 'selected' : ''; ?>>Form Six</option>
                            </select>
                        </div>
                        
                        <!-- Combination Filter -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Combination</label>
                            <select name="combination" class="form-select" id="combinationFilter">
                                <option value="">All Combinations</option>
                                <option value="HGE" <?php echo $filter_combination == 'HGE' ? 'selected' : ''; ?>>HGE</option>
                                <option value="HGL" <?php echo $filter_combination == 'HGL' ? 'selected' : ''; ?>>HGL</option>
                                <option value="HGK" <?php echo $filter_combination == 'HGK' ? 'selected' : ''; ?>>HGK</option>
                                <option value="HKL" <?php echo $filter_combination == 'HKL' ? 'selected' : ''; ?>>HKL</option>
                                <option value="KLF" <?php echo $filter_combination == 'KLF' ? 'selected' : ''; ?>>KLF</option>
                                <option value="EGM" <?php echo $filter_combination == 'EGM' ? 'selected' : ''; ?>>EGM</option>
                                <option value="HLF" <?php echo $filter_combination == 'HLF' ? 'selected' : ''; ?>>HLF</option>
                                <option value="HGF" <?php echo $filter_combination == 'HGF' ? 'selected' : ''; ?>>HGF</option>
                            </select>
                        </div>
                        
                        <!-- Gender Filter -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select" id="genderFilter">
                                <option value="">All Genders</option>
                                <option value="Male" <?php echo $filter_gender == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $filter_gender == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        
                        <!-- Include Date of Birth -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Include DOB?</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="include_dob" value="1" 
                                       id="includeDOB" <?php echo $include_dob ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="includeDOB">
                                    <?php echo $include_dob ? 'Yes' : 'No'; ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Column Inclusion Options -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h6 class="mb-3">Select Columns to Include in Report:</h6>
                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_gender" value="1" 
                                               id="includeGender" <?php echo $include_gender ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeGender">
                                            Include Gender
                                        </label>
                                    </div>
                                </div>
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
                                        <input class="form-check-input" type="checkbox" name="include_admission" value="1" 
                                               id="includeAdmission" <?php echo $include_admission ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeAdmission">
                                            Include Admission No.
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
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_reason" value="1" 
                                               id="includeReason" <?php echo $include_reason ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeReason">
                                            Include Reason
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_date" value="1" 
                                               id="includeDate" <?php echo $include_date ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeDate">
                                            Include Left Date
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
                                    <span class="badge bg-info">Found: <?php echo $total_leavers; ?> leavers</span>
                                    <span class="badge bg-secondary ms-2">Ordered by Year Left (Desc)</span>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search me-2"></i>Apply Filters
                                    </button>
                                    <a href="report_leaver.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-redo me-2"></i>Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-user-slash" style="color: #dc3545;"></i>
                    </div>
                    <h3><?php echo $overall_stats['total'] ?? 0; ?></h3>
                    <p>Total Leavers</p>
                </div>
            </div>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-graduation-cap" style="color: #28a745;"></i>
                    </div>
                    <h3 style="color: #28a745;"><?php echo $overall_stats['graduates'] ?? 0; ?></h3>
                    <p>Graduates</p>
                </div>
            </div>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-sign-out-alt" style="color: #ffc107;"></i>
                    </div>
                    <h3 style="color: #ffc107;"><?php echo $overall_stats['regular_leavers'] ?? 0; ?></h3>
                    <p>Regular Leavers</p>
                </div>
            </div>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-venus-mars" style="color: #3B9DB3;"></i>
                    </div>
                    <h3>
                        <span style="color: #007bff;"><?php echo $overall_stats['males'] ?? 0; ?></span>
                        <span class="text-muted mx-1">/</span>
                        <span style="color: #e83e8c;"><?php echo $overall_stats['females'] ?? 0; ?></span>
                    </h3>
                    <p>Male / Female</p>
                </div>
            </div>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-graduation-cap" style="color: #3B9DB3;"></i>
                    </div>
                    <h3>
                        <span style="color: #28a745;"><?php echo $overall_stats['form_five'] ?? 0; ?></span>
                        <span class="text-muted mx-1">/</span>
                        <span style="color: #17a2b8;"><?php echo $overall_stats['form_six'] ?? 0; ?></span>
                    </h3>
                    <p>Form V / Form VI</p>
                </div>
            </div>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-filter" style="color: #3B9DB3;"></i>
                    </div>
                    <h3 style="color: #3B9DB3;"><?php echo $total_leavers; ?></h3>
                    <p>Filtered Results</p>
                </div>
            </div>
        </div>

        <!-- Export Options Card -->
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
                            <p class="text-muted">Professional PDF report with detailed statistics</p>
                            <?php
                            $export_url = "report_leaver.php?" . http_build_query([
                                'type' => $filter_type,
                                'year' => $filter_year,
                                'class' => $filter_class,
                                'combination' => $filter_combination,
                                'gender' => $filter_gender,
                                'include_dob' => $include_dob ? 1 : 0,
                                'include_gender' => $include_gender ? 1 : 0,
                                'include_class' => $include_class ? 1 : 0,
                                'include_admission' => $include_admission ? 1 : 0,
                                'include_combination' => $include_combination ? 1 : 0,
                                'include_reason' => $include_reason ? 1 : 0,
                                'include_date' => $include_date ? 1 : 0,
                                'export' => 'pdf'
                            ]);
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
                            <p class="text-muted">Download as Excel spreadsheet</p>
                            <?php
                            $export_excel_url = "report_leaver.php?" . http_build_query([
                                'type' => $filter_type,
                                'year' => $filter_year,
                                'class' => $filter_class,
                                'combination' => $filter_combination,
                                'gender' => $filter_gender,
                                'include_dob' => $include_dob ? 1 : 0,
                                'include_gender' => $include_gender ? 1 : 0,
                                'include_class' => $include_class ? 1 : 0,
                                'include_admission' => $include_admission ? 1 : 0,
                                'include_combination' => $include_combination ? 1 : 0,
                                'include_reason' => $include_reason ? 1 : 0,
                                'include_date' => $include_date ? 1 : 0,
                                'export' => 'excel'
                            ]);
                            ?>
                            <a href="<?php echo $export_excel_url; ?>" class="btn btn-success btn-lg">
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
                        <li>Excel export suitable for data analysis</li>
                        <li>Includes comprehensive statistics and combination breakdown</li>
                        <li>Yearly distribution analysis included in PDF</li>
                        <li>Only selected columns will be included in the export</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Preview Card -->
        <div class="card">
            <div class="card-header" style=" background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-eye me-2"></i>Report Preview
                    </h4>
                    <div>
                        <span class="badge bg-light text-dark">
                            <?php echo $total_leavers; ?> leavers
                        </span>
                        <span class="badge bg-info ms-2">
                            <i class="fas fa-sort-numeric-down me-1"></i>Ordered by Year Left
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($total_leavers > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="previewTable">
                        <thead class="table-light">
                            <tr>
                                <th>S/N</th>
                                <th>Index No.</th>
                                <th>Full Name</th>
                                <th>Type</th>
                                <?php if ($include_combination): ?>
                                <th>Combination</th>
                                <?php endif; ?>
                                <?php if ($include_gender): ?>
                                <th>Gender</th>
                                <?php endif; ?>
                                <?php if ($include_class): ?>
                                <th>Class Left</th>
                                <?php endif; ?>
                                <?php if ($include_dob): ?>
                                <th>Date of Birth</th>
                                <?php endif; ?>
                                <?php if ($include_reason): ?>
                                <th>Reason</th>
                                <?php endif; ?>
                                <th>Year Left</th>
                                <?php if ($include_date): ?>
                                <th>Left Date</th>
                                <?php endif; ?>
                                <?php if ($include_admission): ?>
                                <th>Admission No.</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($leavers as $index => $leaver): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($leaver['index_number']); ?></strong>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3">
                                            <?php if ($leaver['sex'] == 'Male'): ?>
                                                <i class="fas fa-male text-primary"></i>
                                            <?php else: ?>
                                                <i class="fas fa-female" style="color: #e83e8c;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($leaver['first_name'] . ' ' . $leaver['last_name']); ?></strong>
                                            <?php if (!empty($leaver['second_name'])): ?>
                                                <div class="small text-muted">
                                                    <?php echo htmlspecialchars($leaver['second_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($leaver['graduation_status'] == 'Graduated'): ?>
                                        <span class="badge bg-success">Graduate</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Leaver</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($include_combination): ?>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($leaver['combination']); ?></span>
                                </td>
                                <?php endif; ?>
                                <?php if ($include_gender): ?>
                                <td>
                                    <span class="badge <?php echo $leaver['sex'] == 'Male' ? 'bg-info' : 'bg-pink'; ?>">
                                        <?php echo htmlspecialchars($leaver['sex']); ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <?php if ($include_class): ?>
                                <td>
                                    <span class="badge <?php echo ($leaver['previous_class'] ?: $leaver['class']) == 'Form Five' ? 'bg-success' : 'bg-info'; ?>">
                                        <?php echo htmlspecialchars($leaver['previous_class'] ?: $leaver['class']); ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <?php if ($include_dob): ?>
                                <td><?php echo htmlspecialchars($leaver['date_of_birth']); ?></td>
                                <?php endif; ?>
                                <?php if ($include_reason): ?>
                                <td>
                                    <small><?php echo htmlspecialchars($leaver['reason'] ?: ($leaver['graduation_status'] == 'Graduated' ? 'Graduated' : 'Not specified')); ?></small>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge bg-dark"><?php echo htmlspecialchars($leaver['year_left']); ?></span>
                                </td>
                                <?php if ($include_date): ?>
                                <td>
                                    <small><?php echo !empty($leaver['left_at']) ? date('d/m/Y', strtotime($leaver['left_at'])) : ''; ?></small>
                                </td>
                                <?php endif; ?>
                                <?php if ($include_admission): ?>
                                <td><?php echo htmlspecialchars($leaver['admission_number']); ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                    <h4>No leavers found</h4>
                    <p class="text-muted">Try adjusting your filter criteria</p>
                    <a href="report_leaver.php" class="btn btn-primary">
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
    const switches = ['includeDOB', 'includeGender', 'includeClass', 'includeAdmission', 'includeCombination', 'includeReason', 'includeDate'];
    
    switches.forEach(switchId => {
        const switchElement = document.getElementById(switchId);
        if (switchElement) {
            const label = switchElement.nextElementSibling;
            switchElement.addEventListener('change', function() {
                label.textContent = this.checked ? 'Yes' : 'No';
            });
        }
    });
    
    // Apply filters on change
    const filters = ['typeFilter', 'yearFilter', 'classFilter', 'combinationFilter', 'genderFilter'];
    filters.forEach(filterId => {
        const element = document.getElementById(filterId);
        if (element) {
            element.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        }
    });
    
    // Add some interactivity to the preview table
    const tableRows = document.querySelectorAll('#previewTable tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgba(59, 157, 179, 0.05)';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
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
            cb.dispatchEvent(new Event('change'));
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

// Print function for leavers report
function printPreview() {
    const printContent = `
        <html>
        <head>
            <title>Leavers Report - Muyovozi High School</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .header h1 { color: #3B9DB3; margin: 0; }
                .header p { color: #666; margin: 5px 0; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background-color: #3B9DB3; color: white; padding: 10px; text-align: left; }
                td { padding: 8px; border-bottom: 1px solid #ddd; }
                .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
                .stats { display: flex; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; }
                .stat-box { background: #f5f5f5; padding: 10px; border-radius: 5px; text-align: center; flex: 1; margin: 5px; min-width: 120px; }
                .badge { padding: 3px 8px; border-radius: 10px; font-size: 12px; }
                .badge-success { background: #28a745; color: white; }
                .badge-warning { background: #ffc107; color: black; }
                .badge-primary { background: #007bff; color: white; }
                .badge-dark { background: #343a40; color: white; }
                @media print {
                    @page { margin: 0.5cm; }
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>MUYOVOZI HIGH SCHOOL</h1>
                <p>Leavers & Graduates Report - Generated on <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
            
            <div class="stats no-print">
                <div class="stat-box">
                    <strong>Total Leavers:</strong><br>
                    <?php echo $total_leavers; ?>
                </div>
                <div class="stat-box">
                    <strong>Type:</strong><br>
                    <?php echo $filter_type ?: 'All'; ?>
                </div>
                <div class="stat-box">
                    <strong>Year:</strong><br>
                    <?php echo $filter_year ?: 'All'; ?>
                </div>
                <div class="stat-box">
                    <strong>Class:</strong><br>
                    <?php echo $filter_class ?: 'All'; ?>
                </div>
                <div class="stat-box">
                    <strong>Combination:</strong><br>
                    <?php echo $filter_combination ?: 'All'; ?>
                </div>
            </div>
            
            <?php 
            echo '<table>';
            echo '<thead><tr>
                    <th>S/N</th>
                    <th>Index No.</th>
                    <th>Full Name</th>
                    <th>Type</th>';
            
            if ($include_combination) echo '<th>Combination</th>';
            if ($include_gender) echo '<th>Gender</th>';
            if ($include_class) echo '<th>Class Left</th>';
            if ($include_dob) echo '<th>Date of Birth</th>';
            if ($include_reason) echo '<th>Reason</th>';
            echo '<th>Year Left</th>';
            if ($include_date) echo '<th>Left Date</th>';
            if ($include_admission) echo '<th>Admission No.</th>';
            
            echo '</tr></thead><tbody>';
            
            foreach($leavers as $index => $leaver) {
                echo '<tr>';
                echo '<td>' . ($index + 1) . '</td>';
                echo '<td>' . $leaver['index_number'] . '</td>';
                echo '<td>' . $leaver['first_name'] . ' ' . $leaver['last_name'] . '</td>';
                echo '<td>' . ($leaver['graduation_status'] == 'Graduated' ? 'Graduate' : 'Leaver') . '</td>';
                
                if ($include_combination) echo '<td>' . $leaver['combination'] . '</td>';
                if ($include_gender) echo '<td>' . $leaver['sex'] . '</td>';
                if ($include_class) echo '<td>' . ($leaver['previous_class'] ?: $leaver['class']) . '</td>';
                if ($include_dob) echo '<td>' . $leaver['date_of_birth'] . '</td>';
                if ($include_reason) {
                    $reason = $leaver['reason'] ?: ($leaver['graduation_status'] == 'Graduated' ? 'Graduated' : 'Not specified');
                    echo '<td>' . $reason . '</td>';
                }
                echo '<td>' . $leaver['year_left'] . '</td>';
                if ($include_date) {
                    $left_date = !empty($leaver['left_at']) ? date('d/m/Y', strtotime($leaver['left_at'])) : '';
                    echo '<td>' . $left_date . '</td>';
                }
                if ($include_admission) echo '<td>' . $leaver['admission_number'] . '</td>';
                
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            ?>
            
            <div class="footer">
                <p>Generated by Administration System</p>
            </div>
            
            <script>
            window.onload = function() {
                window.print();
                setTimeout(function() {
                    window.close();
                }, 500);
            }
            </script>
        </body>
        </html>
</script>

<style>
/* Custom styles for leavers report page */
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

/* Form controls */
.form-select, .form-check-input {
    border-radius: 8px;
}

.form-check-input:checked {
    background-color: #3B9DB3;
    border-color: #3B9DB3;
}

.form-check-label {
    cursor: pointer;
}

.form-check-input {
    cursor: pointer;
}

.btn-lg {
    padding: 12px 30px;
    font-size: 1.1rem;
}

/* Column options styling */
.row.mb-3 h6 {
    color: #3B9DB3;
    font-weight: 600;
}

.form-check {
    padding-left: 2rem;
    margin-bottom: 0.5rem;
}

/* Index number styling */
#previewTable td:nth-child(2) {
    font-family: 'Courier New', monospace;
    font-weight: bold;
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
    
    .form-check {
        padding-left: 1.5rem;
    }
    
    .avatar-circle {
        width: 30px;
        height: 30px;
        margin-right: 8px;
    }
    
    /* Adjust table columns for mobile */
    #previewTable th, #previewTable td {
        padding: 4px 2px;
        font-size: 0.85rem;
    }
}

/* Print button */
@media print {
    .no-print {
        display: none !important;
    }
    
    .card-header, .card-body {
        border: none !important;
    }
    
    .btn {
        display: none !important;
    }
}
</style>

<?php include '../controller/footer.php'; ?>