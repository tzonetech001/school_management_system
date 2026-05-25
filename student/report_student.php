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
    if ($role_id == 1 || $role_id == 2 || $role_id == 3 || $role_id == 12) { // Head Master or Second Master
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
$filter_class = $_GET['class'] ?? '';
$filter_combination = $_GET['combination'] ?? '';
$filter_gender = $_GET['gender'] ?? '';
$filter_status = $_GET['status'] ?? 'Active';
$include_dob = isset($_GET['include_dob']) && $_GET['include_dob'] == 1 ? true : false;
$include_gender = isset($_GET['include_gender']) && $_GET['include_gender'] == 1 ? true : false;
$include_class = isset($_GET['include_class']) && $_GET['include_class'] == 1 ? true : false;
$include_admission = isset($_GET['include_admission']) && $_GET['include_admission'] == 1 ? true : false;
$include_status = isset($_GET['include_status']) && $_GET['include_status'] == 1 ? true : false;
$include_combination = isset($_GET['include_combination']) && $_GET['include_combination'] == 1 ? true : false;

// Build SQL query based on filters
$where_conditions = ["is_leaver = FALSE"];
$params = [];

if (!empty($filter_class)) {
    $where_conditions[] = "class = ?";
    $params[] = $filter_class;
}

if (!empty($filter_combination)) {
    $where_conditions[] = "combination = ?";
    $params[] = $filter_combination;
}

if (!empty($filter_gender)) {
    $where_conditions[] = "sex = ?";
    $params[] = $filter_gender;
}

if (!empty($filter_status)) {
    if ($filter_status == 'Active') {
        $where_conditions[] = "status = 1";
    } else if ($filter_status == 'Inactive') {
        $where_conditions[] = "status = 0";
    }
}

$where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Order by index number in ascending order
$order_by = "ORDER BY 
    CASE 
        WHEN class = 'Form Five' THEN 1
        WHEN class = 'Form Six' THEN 2
        ELSE 3
    END,
    index_number ASC";

// Get filtered students
$sql = "SELECT * FROM students $where_clause $order_by";
$stmt = mysqli_prepare($conn, $sql);

if (!empty($params)) {
    $types = str_repeat('s', count($params));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$students = mysqli_fetch_all($result, MYSQLI_ASSOC);
$total_students = count($students);

// Get statistics for each combination
$combination_stats_sql = "SELECT combination, 
                         COUNT(*) as total,
                         SUM(CASE WHEN sex = 'Male' THEN 1 ELSE 0 END) as males,
                         SUM(CASE WHEN sex = 'Female' THEN 1 ELSE 0 END) as females
                         FROM students 
                         WHERE is_leaver = FALSE AND status = 1
                         GROUP BY combination
                         ORDER BY FIELD(combination, 'HGE', 'HGL', 'HGK', 'HKL', 'KLF', 'EGM', 'HLF', 'HGF')";
$combination_stats_result = mysqli_query($conn, $combination_stats_sql);
$combination_stats = [];
while ($row = mysqli_fetch_assoc($combination_stats_result)) {
    $combination_stats[$row['combination']] = $row;
}

// Handle PDF generation
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once('../tcpdf/tcpdf.php');
    
    class StudentPDF extends TCPDF {
        // Page header
        public function Header() {
            // Logo
            $logo_path = '../muyovozi.png';
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 10, 10, 20, 20, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
                $this->SetFont('helvetica', 'B', 16);
                $this->Cell(0, 0, 'MUYOVOZI HIGH SCHOOL', 0, 1, 'C');
                $this->SetFont('helvetica', '', 10);
                $this->Cell(0, 0, 'Students Report', 0, 1, 'C');
                $this->SetY(35);
            } else {
                $this->SetFont('helvetica', 'B', 16);
                $this->Cell(0, 0, 'MUYOVOZI HIGH SCHOOL', 0, 1, 'C');
                $this->SetFont('helvetica', '', 10);
                $this->Cell(0, 0, 'Students Report', 0, 1, 'C');
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
    $pdf = new StudentPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Muyovozi High School');
    $pdf->SetAuthor('Administrator');
    $pdf->SetTitle('Students Report');
    $pdf->SetSubject('Students List');
    $pdf->SetKeywords('Students, Report, Muyovozi');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'MUYOVOZI HIGH SCHOOL', 'Students Report');
    
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
    
    // Report title with filters
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'STUDENTS DETAILS', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Filter summary
    $pdf->SetFont('helvetica', '', 10);
    $filter_text = "Filters: ";
    if (!empty($filter_class)) $filter_text .= "Class: $filter_class | ";
    if (!empty($filter_combination)) $filter_text .= "Combination: $filter_combination | ";
    if (!empty($filter_gender)) $filter_text .= "Gender: $filter_gender | ";
    $filter_text .= "Status: $filter_status | ";
    $filter_text .= "Total Students: $total_students";
    $pdf->Cell(0, 10, $filter_text, 0, 1);
    $pdf->Ln(3);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 9);
    $header = array('S/N', 'Index No.', 'Full Name');
    
    // Dynamically add columns based on inclusion options
    $col_widths = [12, 25, 60];
    $column_count = 3;
    
    if ($include_combination) {
        $header[] = 'Comb.';
        $col_widths[] = 18;
        $column_count++;
    }
    
    if ($include_gender) {
        $header[] = 'Gender';
        $col_widths[] = 18;
        $column_count++;
    }
    
    if ($include_status) {
        $header[] = 'Status';
        $col_widths[] = 18;
        $column_count++;
    }
    
    if ($include_dob) {
        $header[] = 'Date of Birth';
        $col_widths[] = 25;
        $column_count++;
    }
    
    if ($include_class) {
        $header[] = 'Class';
        $col_widths[] = 20;
        $column_count++;
    }
    
    if ($include_admission) {
        $header[] = 'Admission No.';
        $col_widths[] = 25;
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
    $pdf->SetFont('helvetica', '', 9);
    
    // Table content
    $fill = false;
    $sn = 1;
    
    foreach($students as $student) {
        // Alternate row background
        if($fill) {
            $pdf->SetFillColor(240, 248, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        // Build full name with all three names
        $full_name = trim($student['first_name'] . ' ' . $student['second_name'] . ' ' . $student['last_name']);
        
        $pdf->Cell($col_widths[0], 8, $sn, 1, 0, 'C', $fill);
        $pdf->Cell($col_widths[1], 8, $student['index_number'], 1, 0, 'C', $fill);
        $pdf->Cell($col_widths[2], 8, $full_name, 1, 0, 'L', $fill);
        
        $col_index = 3;
        
        if ($include_combination) {
            $pdf->Cell($col_widths[$col_index], 8, $student['combination'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_gender) {
            $pdf->Cell($col_widths[$col_index], 8, $student['sex'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_status) {
            $pdf->Cell($col_widths[$col_index], 8, $student['status'] ? 'Active' : 'Inactive', 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_dob) {
            $pdf->Cell($col_widths[$col_index], 8, $student['date_of_birth'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_class) {
            $pdf->Cell($col_widths[$col_index], 8, $student['class'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_admission) {
            $pdf->Cell($col_widths[$col_index], 8, $student['admission_number'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        $pdf->Ln();
        $fill = !$fill;
        $sn++;
    }
    
    // Add summary section
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 10, 'COMBINATION STATISTICS', 0, 1);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(59, 157, 179, 10);
    
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
    
    // Output PDF
    $pdf->Output('students_report_' . date('Y-m-d') . '.pdf', 'D');
    exit();
}

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="students_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    $colspan = 3; // S/N, Index No., Full Name are always included
    if ($include_combination) $colspan++;
    if ($include_gender) $colspan++;
    if ($include_status) $colspan++;
    if ($include_dob) $colspan++;
    if ($include_class) $colspan++;
    if ($include_admission) $colspan++;
    
    echo '<table border="1">
        <tr><th colspan="' . $colspan . '">MUYOVOZI HIGH SCHOOL - STUDENTS REPORT</th></tr>
        <tr>
            <th>S/N</th>
            <th>Index No.</th>
            <th>Full Name</th>';
    
    if ($include_combination) {
        echo '<th>Combination</th>';
    }
    
    if ($include_gender) {
        echo '<th>Sex</th>';
    }
    
    if ($include_status) {
        echo '<th>Status</th>';
    }
    
    if ($include_dob) {
        echo '<th>Date of Birth</th>';
    }
    
    if ($include_class) {
        echo '<th>Class</th>';
    }
    
    if ($include_admission) {
        echo '<th>Admission No.</th>';
    }
    
    echo '</tr>';
    
    $sn = 1;
    foreach($students as $student) {
        // Build full name with all three names
        $full_name = trim($student['first_name'] . ' ' . $student['second_name'] . ' ' . $student['last_name']);
        
        echo '<tr>';
        echo '<td>' . $sn . '</td>';
        echo '<td>' . $student['index_number'] . '</td>';
        echo '<td>' . $full_name . '</td>';
        
        if ($include_combination) {
            echo '<td>' . $student['combination'] . '</td>';
        }
        
        if ($include_gender) {
            echo '<td>' . $student['sex'] . '</td>';
        }
        
        if ($include_status) {
            echo '<td>' . ($student['status'] ? 'Active' : 'Inactive') . '</td>';
        }
        
        if ($include_dob) {
            echo '<td>' . $student['date_of_birth'] . '</td>';
        }
        
        if ($include_class) {
            echo '<td>' . $student['class'] . '</td>';
        }
        
        if ($include_admission) {
            echo '<td>' . $student['admission_number'] . '</td>';
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
            <h2 class="page-title">Students Report Generator</h2>
            <div>
                <a href="students.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Students
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
                <form method="GET" action="report_student.php" id="filterForm">
                    <div class="row">
                        <!-- Class Filter -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Class</label>
                            <select name="class" class="form-select" id="classFilter">
                                <option value="">All Classes</option>
                                <option value="Form Five" <?php echo $filter_class == 'Form Five' ? 'selected' : ''; ?>>Form Five</option>
                                <option value="Form Six" <?php echo $filter_class == 'Form Six' ? 'selected' : ''; ?>>Form Six</option>
                            </select>
                        </div>
                        
                        <!-- Combination Filter -->
                        <div class="col-md-3 mb-3">
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
                        
                        <!-- Status Filter -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" id="statusFilter">
                                <option value="Active" <?php echo $filter_status == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $filter_status == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Both" <?php echo $filter_status == 'Both' ? 'selected' : ''; ?>>Both</option>
                            </select>
                        </div>
                        
                        <!-- Include DOB -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Include Date of Birth?</label>
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
                                            Include Sex
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
                                        <input class="form-check-input" type="checkbox" name="include_status" value="1" 
                                               id="includeStatus" <?php echo $include_status ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeStatus">
                                            Include Status
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
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-info">Found: <?php echo $total_students; ?> students</span>
                                    <span class="badge bg-secondary ms-2">Ordered by Index Number</span>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search me-2"></i>Apply Filters
                                    </button>
                                    <a href="report_student.php" class="btn btn-outline-secondary">
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
            <?php
            $stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN class = 'Form Five' THEN 1 ELSE 0 END) as form_five,
                SUM(CASE WHEN class = 'Form Six' THEN 1 ELSE 0 END) as form_six,
                SUM(CASE WHEN sex = 'Male' THEN 1 ELSE 0 END) as males,
                SUM(CASE WHEN sex = 'Female' THEN 1 ELSE 0 END) as females,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive
                FROM students WHERE is_leaver = FALSE";
            
            $stats_result = mysqli_query($conn, $stats_sql);
            $overall_stats = mysqli_fetch_assoc($stats_result);
            ?>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-users" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $overall_stats['total'] ?? 0; ?></h3>
                    <p>Total Students</p>
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
                        <i class="fas fa-user-check" style="color: #3B9DB3;"></i>
                    </div>
                    <h3>
                        <span style="color: #28a745;"><?php echo $overall_stats['active'] ?? 0; ?></span>
                        <span class="text-muted mx-1">/</span>
                        <span style="color: #dc3545;"><?php echo $overall_stats['inactive'] ?? 0; ?></span>
                    </h3>
                    <p>Active / Inactive</p>
                </div>
            </div>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-filter" style="color: #3B9DB3;"></i>
                    </div>
                    <h3 style="color: #3B9DB3;"><?php echo $total_students; ?></h3>
                    <p>Filtered Results</p>
                </div>
            </div>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-columns" style="color: #3B9DB3;"></i>
                    </div>
                    <h3 style="color: #ffc107;">
                        <?php 
                        $column_count = 3; // S/N, Index, Name are always included
                        if ($include_gender) $column_count++;
                        if ($include_class) $column_count++;
                        if ($include_admission) $column_count++;
                        if ($include_status) $column_count++;
                        if ($include_dob) $column_count++;
                        if ($include_combination) $column_count++;
                        echo $column_count;
                        ?>
                    </h3>
                    <p>Columns</p>
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
                            <p class="text-muted">Generate professional PDF report with logo</p>
                            <?php
                            $export_url = "report_student.php?" . http_build_query([
                                'class' => $filter_class,
                                'combination' => $filter_combination,
                                'gender' => $filter_gender,
                                'status' => $filter_status == 'Both' ? '' : $filter_status,
                                'include_dob' => $include_dob ? 1 : 0,
                                'include_gender' => $include_gender ? 1 : 0,
                                'include_class' => $include_class ? 1 : 0,
                                'include_admission' => $include_admission ? 1 : 0,
                                'include_status' => $include_status ? 1 : 0,
                                'include_combination' => $include_combination ? 1 : 0,
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
                            <p class="text-muted">Download as Excel spreadsheet for analysis</p>
                            <?php
                            $export_excel_url = "report_student.php?" . http_build_query([
                                'class' => $filter_class,
                                'combination' => $filter_combination,
                                'gender' => $filter_gender,
                                'status' => $filter_status == 'Both' ? '' : $filter_status,
                                'include_dob' => $include_dob ? 1 : 0,
                                'include_gender' => $include_gender ? 1 : 0,
                                'include_class' => $include_class ? 1 : 0,
                                'include_admission' => $include_admission ? 1 : 0,
                                'include_status' => $include_status ? 1 : 0,
                                'include_combination' => $include_combination ? 1 : 0,
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
                    <strong>Note:</strong> 
                    <ul class="mb-0">
                        <li>The PDF report will include the school logo and professional formatting</li>
                        <li>Excel export is suitable for data analysis and further processing</li>
                        <li>Students are ordered by index number in ascending order</li>
                        <li>Only selected columns will be included in the export</li>
                        <li><strong>Full name now includes first, second, and last name</strong></li>
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
                            <?php echo $total_students; ?> students
                        </span>
                        <span class="badge bg-info ms-2">
                            <i class="fas fa-sort-numeric-up me-1"></i>Ordered by Index
                        </span>
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
                                <th>Index No.</th>
                                <th>Full Name</th>
                                <?php if ($include_combination): ?>
                                <th>Combination</th>
                                <?php endif; ?>
                                <?php if ($include_gender): ?>
                                <th>Gender</th>
                                <?php endif; ?>
                                <?php if ($include_status): ?>
                                <th>Status</th>
                                <?php endif; ?>
                                <?php if ($include_dob): ?>
                                <th>Date of Birth</th>
                                <?php endif; ?>
                                <?php if ($include_class): ?>
                                <th>Class</th>
                                <?php endif; ?>
                                <?php if ($include_admission): ?>
                                <th>Admission No.</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($students as $index => $student): 
                                // Build full name with all three names
                                $full_name = trim($student['first_name'] . ' ' . $student['second_name'] . ' ' . $student['last_name']);
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($student['index_number']); ?></strong>
                                    <?php if (!$student['index_number']): ?>
                                        <span class="badge bg-warning text-dark">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
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
                                            <strong><?php echo htmlspecialchars($full_name); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <?php if ($include_combination): ?>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($student['combination']); ?></span>
                                </td>
                                <?php endif; ?>
                                <?php if ($include_gender): ?>
                                <td>
                                    <span class="badge <?php echo $student['sex'] == 'Male' ? 'bg-info' : 'bg-pink'; ?>">
                                        <?php echo htmlspecialchars($student['sex']); ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <?php if ($include_status): ?>
                                <td>
                                    <span class="badge <?php echo $student['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $student['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <?php if ($include_dob): ?>
                                <td><?php echo htmlspecialchars($student['date_of_birth']); ?></td>
                                <?php endif; ?>
                                <?php if ($include_class): ?>
                                <td>
                                    <span class="badge <?php echo $student['class'] == 'Form Five' ? 'bg-success' : 'bg-info'; ?>">
                                        <?php echo htmlspecialchars($student['class']); ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <?php if ($include_admission): ?>
                                <td><?php echo htmlspecialchars($student['admission_number']); ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h4>No students found</h4>
                    <p class="text-muted">Try adjusting your filter criteria</p>
                    <a href="report_student.php" class="btn btn-primary">
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
    const switches = ['includeDOB', 'includeGender', 'includeClass', 'includeAdmission', 'includeStatus', 'includeCombination'];
    
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
    const filters = ['classFilter', 'combinationFilter', 'genderFilter', 'statusFilter'];
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

// Print function
function printPreview() {
    const printContent = `
        <html>
        <head>
            <title>Students Report - Muyovozi High School</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .header h1 { color: #3B9DB3; margin: 0; }
                .header p { color: #666; margin: 5px 0; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background-color: #3B9DB3; color: white; padding: 10px; text-align: left; }
                td { padding: 8px; border-bottom: 1px solid #ddd; }
                .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
                .stats { display: flex; justify-content: space-between; margin-bottom: 20px; }
                .stat-box { background: #f5f5f5; padding: 10px; border-radius: 5px; text-align: center; flex: 1; margin: 0 5px; }
                .badge { padding: 3px 8px; border-radius: 10px; font-size: 12px; }
                .badge-primary { background: #007bff; color: white; }
                .badge-success { background: #28a745; color: white; }
                .badge-info { background: #17a2b8; color: white; }
                @media print {
                    @page { margin: 0.5cm; }
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>MUYOVOZI HIGH SCHOOL</h1>
                <p>Students Report - Generated on <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
            
            <div class="stats no-print">
                <div class="stat-box">
                    <strong>Total Students:</strong><br>
                    <?php echo $total_students; ?>
                </div>
                <div class="stat-box">
                    <strong>Class:</strong><br>
                    <?php echo $filter_class ?: 'All'; ?>
                </div>
                <div class="stat-box">
                    <strong>Combination:</strong><br>
                    <?php echo $filter_combination ?: 'All'; ?>
                </div>
                <div class="stat-box">
                    <strong>Gender:</strong><br>
                    <?php echo $filter_gender ?: 'All'; ?>
                </div>
            </div>
            
            <?php 
            echo '<table>';
            echo '<thead><tr>
                    <th>S/N</th>
                    <th>Index No.</th>
                    <th>Full Name</th>';
            
            if ($include_combination) echo '<th>Combination</th>';
            if ($include_gender) echo '<th>Sex</th>';
            if ($include_status) echo '<th>Status</th>';
            if ($include_dob) echo '<th>Date of Birth</th>';
            if ($include_class) echo '<th>Class</th>';
            if ($include_admission) echo '<th>Admission No.</th>';
            
            echo '</tr></thead><tbody>';
            
            foreach($students as $index => $student) {
                $full_name = trim($student['first_name'] . ' ' . $student['second_name'] . ' ' . $student['last_name']);
                
                echo '<tr>';
                echo '<td>' . ($index + 1) . '</td>';
                echo '<td>' . $student['index_number'] . '</td>';
                echo '<td>' . $full_name . '</td>';
                
                if ($include_combination) echo '<td>' . $student['combination'] . '</td>';
                if ($include_gender) echo '<td>' . $student['sex'] . '</td>';
                if ($include_status) echo '<td>' . ($student['status'] ? 'Active' : 'Inactive') . '</td>';
                if ($include_dob) echo '<td>' . $student['date_of_birth'] . '</td>';
                if ($include_class) echo '<td>' . $student['class'] . '</td>';
                if ($include_admission) echo '<td>' . $student['admission_number'] . '</td>';
                
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