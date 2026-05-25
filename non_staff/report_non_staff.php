<?php
// report_non_staff.php - Non-Staff Employee Report Generator
session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'] ?? 0;

// ==================== LOAD THEME SETTINGS ====================
$theme_settings = [];
$query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id";
$result = mysqli_query($conn, $query);
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $theme_settings[$row['setting_key']] = $row['setting_value'];
    }
}

$preferences = [];
$prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = $admin_id";
$prefs_result = mysqli_query($conn, $prefs_query);
if ($prefs_result && mysqli_num_rows($prefs_result) > 0) {
    while ($row = mysqli_fetch_assoc($prefs_result)) {
        $preferences[$row['preference_key']] = $row['preference_value'];
    }
}

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
    if (array_key_exists($key, $colors) && $value !== null) {
        $colors[$key] = $value;
    }
}

$pref_defaults = [
    'background_opacity' => '65',
    'animations' => '1',
    'font_size' => '16',
    'compact_mode' => '0',
    'background_option' => 'image',
    'sidebar_collapsed' => '0',
    'animation_speed' => 'normal'
];

foreach ($pref_defaults as $key => $default) {
    if (!isset($preferences[$key]) || $preferences[$key] === null) {
        $preferences[$key] = $default;
    }
}

$bg_opacity = isset($preferences['background_opacity']) ? $preferences['background_opacity'] / 100 : 0.65;
$animations_enabled = $preferences['animations'];
$font_size = $preferences['font_size'];
$compact_mode = $preferences['compact_mode'];
$bg_option = $preferences['background_option'];
$sidebar_collapsed = $preferences['sidebar_collapsed'];
$animation_speed = $preferences['animation_speed'];

$animation_speeds = ['slow' => '0.5s', 'normal' => '0.3s', 'fast' => '0.15s'];
$animation_duration = isset($animation_speeds[$animation_speed]) ? $animation_speeds[$animation_speed] : '0.3s';

$font_size_map = ['10' => '10px', '12' => '12px', '14' => '14px', '16' => '16px', '18' => '18px'];
$font_size_value = isset($font_size_map[$font_size]) ? $font_size_map[$font_size] : '16px';

$background_colors = ['gray' => '#e9ecef', 'eye_care' => '#c7e9c0', 'milk' => '#fdf5e6', 'dark_light' => '#2d2d2d'];

if ($bg_option === 'image') {
    $bg_style = "linear-gradient(rgba(255,255,255,{$bg_opacity}), rgba(255,255,255,{$bg_opacity})), url('../muyovozi.png') no-repeat center center fixed";
    $bg_size = 'cover';
} else {
    $bg_color = isset($background_colors[$bg_option]) ? $background_colors[$bg_option] : '#e9ecef';
    $bg_style = $bg_color;
    $bg_size = 'auto';
}

// ==================== PERMISSION CHECK ====================
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
    if ($role_id == 1 || $role_id == 2) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view non-staff employees.";
    header("Location: ../404.php");
    exit();
}

// ==================== FILTERS ====================
$filter_position = $_GET['position'] ?? '';
$filter_gender = $_GET['gender'] ?? '';
$filter_status = $_GET['status'] ?? 'Active';
$filter_contract = $_GET['contract'] ?? '';
$filter_has_nida = $_GET['has_nida'] ?? '';
$filter_department = $_GET['department'] ?? '';

// Column inclusion options
$include_gender = isset($_GET['include_gender']) && $_GET['include_gender'] == 1 ? true : false;
$include_phone = isset($_GET['include_phone']) && $_GET['include_phone'] == 1 ? true : false;
$include_status = isset($_GET['include_status']) && $_GET['include_status'] == 1 ? true : false;
$include_contract = isset($_GET['include_contract']) && $_GET['include_contract'] == 1 ? true : false;
$include_nida = isset($_GET['include_nida']) && $_GET['include_nida'] == 1 ? true : false;
$include_employment_date = isset($_GET['include_employment_date']) && $_GET['include_employment_date'] == 1 ? true : false;
$include_department = isset($_GET['include_department']) && $_GET['include_department'] == 1 ? true : false;
$include_address = isset($_GET['include_address']) && $_GET['include_address'] == 1 ? true : false;

// ==================== BUILD QUERY ====================
$where_conditions = [];
$params = [];
$param_types = "";

$sql = "SELECT * FROM non_staff WHERE 1=1";

if (!empty($filter_position)) {
    $where_conditions[] = "position = ?";
    $params[] = $filter_position;
    $param_types .= "s";
}

if (!empty($filter_gender)) {
    $where_conditions[] = "sex = ?";
    $params[] = $filter_gender;
    $param_types .= "s";
}

if (!empty($filter_status)) {
    if ($filter_status == 'Active') {
        $where_conditions[] = "status = 1";
    } else if ($filter_status == 'Inactive') {
        $where_conditions[] = "status = 0";
    }
}

if (!empty($filter_contract)) {
    $where_conditions[] = "contract_type = ?";
    $params[] = $filter_contract;
    $param_types .= "s";
}

if (!empty($filter_department)) {
    $where_conditions[] = "department = ?";
    $params[] = $filter_department;
    $param_types .= "s";
}

if ($filter_has_nida == 'yes') {
    $where_conditions[] = "nida IS NOT NULL AND nida != ''";
} else if ($filter_has_nida == 'no') {
    $where_conditions[] = "(nida IS NULL OR nida = '')";
}

// Add WHERE clause if conditions exist
if (count($where_conditions) > 0) {
    $sql .= " AND " . implode(" AND ", $where_conditions);
}

// ORDER BY
$sql .= " ORDER BY status DESC, first_name, last_name";

// Execute query
$employees = [];
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $sql);
}

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $employees[] = $row;
    }
}

$total_employees = count($employees);

// ==================== GET FILTER OPTIONS ====================
// Get all positions for filter dropdown
$positions_sql = "SELECT DISTINCT position FROM non_staff WHERE position IS NOT NULL ORDER BY position";
$positions_result = mysqli_query($conn, $positions_sql);
$all_positions = [];
while ($row = mysqli_fetch_assoc($positions_result)) {
    $all_positions[] = $row['position'];
}

// Get all departments for filter dropdown
$departments_sql = "SELECT DISTINCT department FROM non_staff WHERE department IS NOT NULL ORDER BY department";
$departments_result = mysqli_query($conn, $departments_sql);
$all_departments = [];
while ($row = mysqli_fetch_assoc($departments_result)) {
    $all_departments[] = $row['department'];
}

// Get all contract types
$contract_types = ['Permanent', 'Contract', 'Temporary', 'Volunteer'];

// ==================== GET STATISTICS ====================
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN sex = 'Male' THEN 1 ELSE 0 END) as males,
    SUM(CASE WHEN sex = 'Female' THEN 1 ELSE 0 END) as females,
    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive,
    SUM(CASE WHEN nida IS NOT NULL AND nida != '' THEN 1 ELSE 0 END) as has_nida
    FROM non_staff";
$stats_result = mysqli_query($conn, $stats_sql);
$overall_stats = mysqli_fetch_assoc($stats_result);

// Position statistics
$position_stats = [];
$pos_stats_sql = "SELECT 
    position,
    COUNT(*) as total,
    SUM(CASE WHEN sex = 'Male' THEN 1 ELSE 0 END) as males,
    SUM(CASE WHEN sex = 'Female' THEN 1 ELSE 0 END) as females,
    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive
    FROM non_staff 
    WHERE position IS NOT NULL
    GROUP BY position
    ORDER BY total DESC";
$pos_stats_result = mysqli_query($conn, $pos_stats_sql);
while ($row = mysqli_fetch_assoc($pos_stats_result)) {
    $position_stats[] = $row;
}

// Contract type statistics
$contract_stats = [];
$contract_stats_sql = "SELECT 
    contract_type,
    COUNT(*) as total,
    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active
    FROM non_staff 
    GROUP BY contract_type
    ORDER BY total DESC";
$contract_stats_result = mysqli_query($conn, $contract_stats_sql);
while ($row = mysqli_fetch_assoc($contract_stats_result)) {
    $contract_stats[] = $row;
}

// ==================== PDF GENERATION ====================
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once('../tcpdf/tcpdf.php');
    
    class NonStaffPDF extends TCPDF {
        // Page header
        public function Header() {
            // Logo
            $logo_path = '../muyovozi.png';
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 10, 10, 20, 20, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
                $this->SetFont('helvetica', 'B', 16);
                $this->Cell(0, 0, 'MUYOVOZI HIGH SCHOOL', 0, 1, 'C');
                $this->SetFont('helvetica', '', 10);
                $this->Cell(0, 0, 'Non-Staff Employees Report', 0, 1, 'C');
                $this->SetY(35);
            } else {
                $this->SetFont('helvetica', 'B', 16);
                $this->Cell(0, 0, 'MUYOVOZI HIGH SCHOOL', 0, 1, 'C');
                $this->SetFont('helvetica', '', 10);
                $this->Cell(0, 0, 'Non-Staff Employees Report', 0, 1, 'C');
                $this->SetY(30);
            }
            $this->Line(10, $this->GetY() + 0.05, 200, $this->GetY() + 0.05);
            $this->Ln(8);
        }
        
        // Page footer
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages() . ' | Generated: ' . date('Y-m-d H:i:s'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }
    
    // Create new PDF document
    $pdf = new NonStaffPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Muyovozi High School');
    $pdf->SetAuthor('Administrator');
    $pdf->SetTitle('Non-Staff Employees Report');
    $pdf->SetSubject('Non-Staff Employees List');
    $pdf->SetKeywords('Employees, Staff, Report, Muyovozi');
    
    // Set margins
    $pdf->SetMargins(15, 35, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 20);
    
    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Add a page
    $pdf->AddPage();
    
    // Report title with filters
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'NON-STAFF EMPLOYEES DETAILS', 0, 1, 'C');
    $pdf->Ln(3);
    
    // Filter summary
    $pdf->SetFont('helvetica', '', 10);
    $filter_text = "Filters: ";
    if (!empty($filter_position)) $filter_text .= "Position: $filter_position | ";
    if (!empty($filter_gender)) $filter_text .= "Gender: $filter_gender | ";
    if (!empty($filter_status) && $filter_status != 'Both') $filter_text .= "Status: $filter_status | ";
    if (!empty($filter_contract)) $filter_text .= "Contract: $filter_contract | ";
    if (!empty($filter_department)) $filter_text .= "Department: $filter_department | ";
    if (!empty($filter_has_nida)) $filter_text .= "NIDA: " . ($filter_has_nida == 'yes' ? 'Has NIDA' : 'No NIDA') . " | ";
    $filter_text .= "Total Employees: $total_employees";
    $pdf->Cell(0, 8, $filter_text, 0, 1);
    $pdf->Ln(5);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 9);
    $header = array('S/N', 'Full Name', 'Position');
    
    // Dynamically add columns based on inclusion options
    $col_widths = [12, 50, 35];
    $column_count = 3;
    
    if ($include_department) {
        $header[] = 'Department';
        $col_widths[] = 25;
        $column_count++;
    }
    
    if ($include_gender) {
        $header[] = 'Gender';
        $col_widths[] = 15;
        $column_count++;
    }
    
    if ($include_status) {
        $header[] = 'Status';
        $col_widths[] = 15;
        $column_count++;
    }
    
    if ($include_contract) {
        $header[] = 'Contract';
        $col_widths[] = 20;
        $column_count++;
    }
    
    if ($include_phone) {
        $header[] = 'Phone';
        $col_widths[] = 25;
        $column_count++;
    }
    
    if ($include_nida) {
        $header[] = 'NIDA';
        $col_widths[] = 25;
        $column_count++;
    }
    
    if ($include_employment_date) {
        $header[] = 'Employed';
        $col_widths[] = 20;
        $column_count++;
    }
    
    if ($include_address) {
        $header[] = 'Address';
        $col_widths[] = 30;
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
    
    foreach($employees as $employee) {
        // Check if we need a new page
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            // Re-print header
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(59, 157, 179);
            $pdf->SetTextColor(255);
            for($i = 0; $i < count($header); $i++) {
                $pdf->Cell($col_widths[$i], 8, $header[$i], 1, 0, 'C', 1);
            }
            $pdf->Ln();
            $pdf->SetTextColor(0);
            $pdf->SetFont('helvetica', '', 8);
            $fill = false;
        }
        
        // Alternate row background
        if($fill) {
            $pdf->SetFillColor(240, 248, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell($col_widths[0], 7, $sn, 1, 0, 'C', $fill);
        
        // Full Name
        $fullName = $employee['first_name'];
        if (!empty($employee['middle_name'])) {
            $fullName .= ' ' . $employee['middle_name'];
        }
        $fullName .= ' ' . $employee['last_name'];
        $pdf->Cell($col_widths[1], 7, $fullName, 1, 0, 'L', $fill);
        
        // Position
        $pdf->Cell($col_widths[2], 7, $employee['position'], 1, 0, 'L', $fill);
        
        $col_index = 3;
        
        if ($include_department) {
            $pdf->Cell($col_widths[$col_index], 7, $employee['department'] ?? 'N/A', 1, 0, 'L', $fill);
            $col_index++;
        }
        
        if ($include_gender) {
            $pdf->Cell($col_widths[$col_index], 7, $employee['sex'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_status) {
            $status_text = $employee['status'] ? 'Active' : 'Inactive';
            $pdf->SetTextColor($employee['status'] ? 40 : 220, $employee['status'] ? 167 : 53, $employee['status'] ? 69 : 69);
            $pdf->Cell($col_widths[$col_index], 7, $status_text, 1, 0, 'C', $fill);
            $pdf->SetTextColor(0);
            $col_index++;
        }
        
        if ($include_contract) {
            $pdf->Cell($col_widths[$col_index], 7, $employee['contract_type'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_phone) {
            $pdf->Cell($col_widths[$col_index], 7, $employee['phone_number'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_nida) {
            $pdf->Cell($col_widths[$col_index], 7, $employee['nida'] ?: 'N/A', 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_employment_date) {
            $pdf->Cell($col_widths[$col_index], 7, date('Y-m-d', strtotime($employee['employment_date'])), 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_address) {
            $address = $employee['address'] ?? '';
            $address = strlen($address) > 35 ? substr($address, 0, 32) . '...' : $address;
            $pdf->Cell($col_widths[$col_index], 7, $address, 1, 0, 'L', $fill);
            $col_index++;
        }
        
        $pdf->Ln();
        $fill = !$fill;
        $sn++;
    }
    
    // ==================== STATISTICS SECTION ====================
    $pdf->Ln(8);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'STATISTICS SUMMARY', 0, 1, 'L');
    $pdf->Ln(2);
    
    // Overall Statistics
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Overall Statistics:', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    
    $pdf->SetFillColor(248, 249, 250);
    $pdf->SetTextColor(0);
    
    $stats_data = [
        ['Total Employees', $overall_stats['total']],
        ['Male / Female', $overall_stats['males'] . ' / ' . $overall_stats['females']],
        ['Active / Inactive', $overall_stats['active'] . ' / ' . $overall_stats['inactive']],
        ['Have NIDA', $overall_stats['has_nida']]
    ];
    
    foreach($stats_data as $idx => $stat) {
        $pdf->Cell(60, 6, $stat[0], 1, 0, 'L', true);
        $pdf->Cell(40, 6, $stat[1], 1, 1, 'L', true);
    }
    
    $pdf->Ln(4);
    
    // Position Statistics
    if (!empty($position_stats)) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Position-wise Statistics:', 0, 1);
        $pdf->SetFont('helvetica', 'B', 9);
        
        $pos_cols = ['Position', 'Total', 'Male', 'Female', 'Active', 'Inactive'];
        $pos_widths = [50, 20, 20, 20, 20, 20];
        
        $pdf->SetFillColor(59, 157, 179);
        $pdf->SetTextColor(255);
        for($i = 0; $i < count($pos_cols); $i++) {
            $pdf->Cell($pos_widths[$i], 7, $pos_cols[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();
        
        $pdf->SetTextColor(0);
        $pdf->SetFont('helvetica', '', 8);
        $pos_fill = false;
        
        foreach($position_stats as $stat) {
            if($pos_fill) {
                $pdf->SetFillColor(240, 248, 250);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $pdf->Cell($pos_widths[0], 6, $stat['position'], 1, 0, 'L', $pos_fill);
            $pdf->Cell($pos_widths[1], 6, $stat['total'], 1, 0, 'C', $pos_fill);
            $pdf->Cell($pos_widths[2], 6, $stat['males'], 1, 0, 'C', $pos_fill);
            $pdf->Cell($pos_widths[3], 6, $stat['females'], 1, 0, 'C', $pos_fill);
            $pdf->Cell($pos_widths[4], 6, $stat['active'], 1, 0, 'C', $pos_fill);
            $pdf->Cell($pos_widths[5], 6, $stat['inactive'], 1, 0, 'C', $pos_fill);
            $pdf->Ln();
            $pos_fill = !$pos_fill;
        }
        $pdf->Ln(4);
    }
    
    // Contract Type Statistics
    if (!empty($contract_stats)) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Contract Type Statistics:', 0, 1);
        $pdf->SetFont('helvetica', 'B', 9);
        
        $contract_cols = ['Contract Type', 'Total', 'Active'];
        $contract_widths = [60, 30, 30];
        
        $pdf->SetFillColor(59, 157, 179);
        $pdf->SetTextColor(255);
        for($i = 0; $i < count($contract_cols); $i++) {
            $pdf->Cell($contract_widths[$i], 7, $contract_cols[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();
        
        $pdf->SetTextColor(0);
        $pdf->SetFont('helvetica', '', 8);
        $contract_fill = false;
        
        foreach($contract_stats as $stat) {
            if($contract_fill) {
                $pdf->SetFillColor(240, 248, 250);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $pdf->Cell($contract_widths[0], 6, $stat['contract_type'], 1, 0, 'L', $contract_fill);
            $pdf->Cell($contract_widths[1], 6, $stat['total'], 1, 0, 'C', $contract_fill);
            $pdf->Cell($contract_widths[2], 6, $stat['active'], 1, 0, 'C', $contract_fill);
            $pdf->Ln();
            $contract_fill = !$contract_fill;
        }
    }
    
    // Output PDF
    $pdf->Output('non_staff_report_' . date('Y-m-d') . '.pdf', 'D');
    exit();
}

// ==================== EXCEL EXPORT ====================
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="non_staff_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    $colspan = 3; // S/N, Full Name, Position are always included
    if ($include_department) $colspan++;
    if ($include_gender) $colspan++;
    if ($include_status) $colspan++;
    if ($include_contract) $colspan++;
    if ($include_phone) $colspan++;
    if ($include_nida) $colspan++;
    if ($include_employment_date) $colspan++;
    if ($include_address) $colspan++;
    
    echo '<html><head><title>Non-Staff Employees Report</title></head><body>';
    echo '<table border="1">';
    echo '<tr><th colspan="' . $colspan . '" style="background-color:#3B9DB3; color:white;">MUYOVOZI HIGH SCHOOL - NON-STAFF EMPLOYEES REPORT</th></tr>';
    echo '<tr><th colspan="' . $colspan . '">Generated: ' . date('Y-m-d H:i:s') . '</th></tr>';
    echo '<tr><th colspan="' . $colspan . '">Total Employees: ' . $total_employees . '</th></tr>';
    echo '<tr><th colspan="' . $colspan . '"></th></tr>';
    echo '<tr>
            <th>S/N</th>
            <th>Full Name</th>
            <th>Position</th>';
    
    if ($include_department) echo '<th>Department</th>';
    if ($include_gender) echo '<th>Gender</th>';
    if ($include_status) echo '<th>Status</th>';
    if ($include_contract) echo '<th>Contract Type</th>';
    if ($include_phone) echo '<th>Phone</th>';
    if ($include_nida) echo '<th>NIDA</th>';
    if ($include_employment_date) echo '<th>Employment Date</th>';
    if ($include_address) echo '<th>Address</th>';
    
    echo '</tr>';
    
    $sn = 1;
    foreach($employees as $employee) {
        echo '<tr>';
        echo '<td>' . $sn . '</td>';
        
        // Full Name
        $fullName = $employee['first_name'];
        if (!empty($employee['middle_name'])) {
            $fullName .= ' ' . $employee['middle_name'];
        }
        $fullName .= ' ' . $employee['last_name'];
        echo '<td>' . htmlspecialchars($fullName) . '</td>';
        
        echo '<td>' . htmlspecialchars($employee['position']) . '</td>';
        
        if ($include_department) echo '<td>' . htmlspecialchars($employee['department'] ?? 'N/A') . '</td>';
        if ($include_gender) echo '<td>' . htmlspecialchars($employee['sex']) . '</td>';
        if ($include_status) echo '<td>' . ($employee['status'] ? 'Active' : 'Inactive') . '</td>';
        if ($include_contract) echo '<td>' . htmlspecialchars($employee['contract_type']) . '</td>';
        if ($include_phone) echo '<td>' . htmlspecialchars($employee['phone_number']) . '</td>';
        if ($include_nida) echo '<td>' . htmlspecialchars($employee['nida'] ?: 'N/A') . '</td>';
        if ($include_employment_date) echo '<td>' . date('Y-m-d', strtotime($employee['employment_date'])) . '</td>';
        if ($include_address) echo '<td>' . htmlspecialchars($employee['address'] ?? 'N/A') . '</td>';
        
        echo '</tr>';
        $sn++;
    }
    
    // Add statistics section
    echo '<tr><td colspan="' . $colspan . '" style="background-color:#e9ecef;"><strong>STATISTICS SUMMARY</strong></td></tr>';
    echo '<tr><td colspan="' . $colspan . '">Total Employees: ' . $overall_stats['total'] . '</td></tr>';
    echo '<tr><td colspan="' . $colspan . '">Male: ' . $overall_stats['males'] . ' | Female: ' . $overall_stats['females'] . '</td></tr>';
    echo '<tr><td colspan="' . $colspan . '">Active: ' . $overall_stats['active'] . ' | Inactive: ' . $overall_stats['inactive'] . '</td></tr>';
    echo '<tr><td colspan="' . $colspan . '">Have NIDA: ' . $overall_stats['has_nida'] . '</td></tr>';
    
    echo '</table></body></html>';
    exit();
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="page-title mb-0">
                <i class="fas fa-chart-bar me-2" style="color: var(--primary-color, #3B9DB3);"></i> 
                Non-Staff Employees Report
            </h2>
            <div>
                <a href="non_staff.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Management
                </a>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0">
                    <i class="fas fa-filter me-2"></i>Filter & Column Options
                </h4>
            </div>
            <div class="card-body">
                <form method="GET" action="report_non_staff.php" id="filterForm">
                    <div class="row">
                        <!-- Position Filter -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Position</label>
                            <select name="position" class="form-select">
                                <option value="">All Positions</option>
                                <?php foreach($all_positions as $position): ?>
                                <option value="<?php echo htmlspecialchars($position); ?>" 
                                    <?php echo $filter_position == $position ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($position); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Department Filter -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Department</label>
                            <select name="department" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach($all_departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" 
                                    <?php echo $filter_department == $dept ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Gender Filter -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">All Genders</option>
                                <option value="Male" <?php echo $filter_gender == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $filter_gender == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        
                        <!-- Status Filter -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active" <?php echo $filter_status == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $filter_status == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Both" <?php echo $filter_status == 'Both' ? 'selected' : ''; ?>>Both</option>
                            </select>
                        </div>
                        
                        <!-- Contract Type Filter -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Contract Type</label>
                            <select name="contract" class="form-select">
                                <option value="">All Contract Types</option>
                                <?php foreach($contract_types as $type): ?>
                                <option value="<?php echo $type; ?>" 
                                    <?php echo $filter_contract == $type ? 'selected' : ''; ?>>
                                    <?php echo $type; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Has NIDA?</label>
                            <select name="has_nida" class="form-select">
                                <option value="">All</option>
                                <option value="yes" <?php echo $filter_has_nida == 'yes' ? 'selected' : ''; ?>>Has NIDA</option>
                                <option value="no" <?php echo $filter_has_nida == 'no' ? 'selected' : ''; ?>>No NIDA</option>
                            </select>
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
                                        <label class="form-check-label" for="includeGender">Gender</label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_phone" value="1" 
                                               id="includePhone" <?php echo $include_phone ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includePhone">Phone</label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_status" value="1" 
                                               id="includeStatus" <?php echo $include_status ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeStatus">Status</label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_contract" value="1" 
                                               id="includeContract" <?php echo $include_contract ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeContract">Contract Type</label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_nida" value="1" 
                                               id="includeNIDA" <?php echo $include_nida ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeNIDA">NIDA</label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_employment_date" value="1" 
                                               id="includeEmploymentDate" <?php echo $include_employment_date ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeEmploymentDate">Employment Date</label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_department" value="1" 
                                               id="includeDepartment" <?php echo $include_department ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeDepartment">Department</label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_address" value="1" 
                                               id="includeAddress" <?php echo $include_address ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeAddress">Address</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-info">Found: <?php echo $total_employees; ?> employees</span>
                                    <span class="badge bg-secondary ms-2">Ordered by Name</span>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search me-2"></i>Apply Filters
                                    </button>
                                    <a href="report_non_staff.php" class="btn btn-outline-secondary">
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
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-users" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $overall_stats['total'] ?? 0; ?></h3>
                    <p>Total Employees</p>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
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
            
            <div class="col-md-3 col-sm-6 mb-3">
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
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-id-card" style="color: #3B9DB3;"></i>
                    </div>
                    <h3 style="color: #ffc107;"><?php echo $overall_stats['has_nida'] ?? 0; ?></h3>
                    <p>Have NIDA</p>
                </div>
            </div>
        </div>

        <!-- Export Options Card -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
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
                            <p class="text-muted">Generate professional PDF report with logo and statistics</p>
                            <?php
                            $export_url = "report_non_staff.php?" . http_build_query([
                                'position' => $filter_position,
                                'gender' => $filter_gender,
                                'status' => $filter_status == 'Both' ? '' : $filter_status,
                                'contract' => $filter_contract,
                                'department' => $filter_department,
                                'has_nida' => $filter_has_nida,
                                'include_gender' => $include_gender ? 1 : 0,
                                'include_phone' => $include_phone ? 1 : 0,
                                'include_status' => $include_status ? 1 : 0,
                                'include_contract' => $include_contract ? 1 : 0,
                                'include_nida' => $include_nida ? 1 : 0,
                                'include_employment_date' => $include_employment_date ? 1 : 0,
                                'include_department' => $include_department ? 1 : 0,
                                'include_address' => $include_address ? 1 : 0,
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
                            $export_excel_url = "report_non_staff.php?" . http_build_query([
                                'position' => $filter_position,
                                'gender' => $filter_gender,
                                'status' => $filter_status == 'Both' ? '' : $filter_status,
                                'contract' => $filter_contract,
                                'department' => $filter_department,
                                'has_nida' => $filter_has_nida,
                                'include_gender' => $include_gender ? 1 : 0,
                                'include_phone' => $include_phone ? 1 : 0,
                                'include_status' => $include_status ? 1 : 0,
                                'include_contract' => $include_contract ? 1 : 0,
                                'include_nida' => $include_nida ? 1 : 0,
                                'include_employment_date' => $include_employment_date ? 1 : 0,
                                'include_department' => $include_department ? 1 : 0,
                                'include_address' => $include_address ? 1 : 0,
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
                        <li>Employees are ordered by name in alphabetical order</li>
                        <li>Only selected columns will be included in the export</li>
                        <li>Statistics section includes position-wise and contract type breakdowns</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Preview Card -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-eye me-2"></i>Report Preview
                    </h4>
                    <div>
                        <span class="badge bg-light text-dark">
                            <?php echo $total_employees; ?> employees
                        </span>
                        <span class="badge bg-info ms-2">
                            <i class="fas fa-sort-alpha-up me-1"></i>Ordered by Name
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($total_employees > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="previewTable">
                        <thead class="table-light">
                            <tr>
                                <th>S/N</th>
                                <th>Full Name</th>
                                <th>Position</th>
                                <?php if ($include_department): ?>
                                <th>Department</th>
                                <?php endif; ?>
                                <?php if ($include_gender): ?>
                                <th>Gender</th>
                                <?php endif; ?>
                                <?php if ($include_status): ?>
                                <th>Status</th>
                                <?php endif; ?>
                                <?php if ($include_contract): ?>
                                <th>Contract</th>
                                <?php endif; ?>
                                <?php if ($include_phone): ?>
                                <th>Phone</th>
                                <?php endif; ?>
                                <?php if ($include_nida): ?>
                                <th>NIDA</th>
                                <?php endif; ?>
                                <?php if ($include_employment_date): ?>
                                <th>Employed</th>
                                <?php endif; ?>
                                <?php if ($include_address): ?>
                                <th>Address</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($employees as $index => $employee): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3">
                                            <?php if ($employee['sex'] == 'Male'): ?>
                                                <i class="fas fa-male text-primary"></i>
                                            <?php else: ?>
                                                <i class="fas fa-female" style="color: #e83e8c;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong>
                                                <?php 
                                                $fullName = htmlspecialchars($employee['first_name']);
                                                if (!empty($employee['middle_name'])) {
                                                    $fullName .= ' ' . htmlspecialchars($employee['middle_name']);
                                                }
                                                $fullName .= ' ' . htmlspecialchars($employee['last_name']);
                                                echo $fullName;
                                                ?>
                                            </strong>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($employee['position']); ?></span></td>
                                
                                <?php if ($include_department): ?>
                                <td><?php echo htmlspecialchars($employee['department'] ?? '-'); ?></td>
                                <?php endif; ?>
                                
                                <?php if ($include_gender): ?>
                                <td>
                                    <span class="badge <?php echo $employee['sex'] == 'Male' ? 'bg-info' : 'bg-pink'; ?>">
                                        <?php echo htmlspecialchars($employee['sex']); ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                
                                <?php if ($include_status): ?>
                                <td>
                                    <span class="badge <?php echo $employee['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $employee['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                
                                <?php if ($include_contract): ?>
                                <td><?php echo htmlspecialchars($employee['contract_type']); ?></td>
                                <?php endif; ?>
                                
                                <?php if ($include_phone): ?>
                                <td><?php echo htmlspecialchars($employee['phone_number']); ?></td>
                                <?php endif; ?>
                                
                                <?php if ($include_nida): ?>
                                <td>
                                    <?php if (!empty($employee['nida'])): ?>
                                        <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($employee['nida']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                
                                <?php if ($include_employment_date): ?>
                                <td><?php echo date('Y-m-d', strtotime($employee['employment_date'])); ?></td>
                                <?php endif; ?>
                                
                                <?php if ($include_address): ?>
                                <td><?php echo htmlspecialchars(substr($employee['address'] ?? '', 0, 50)) . (strlen($employee['address'] ?? '') > 50 ? '...' : ''); ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                    <h4>No employees found</h4>
                    <p class="text-muted">Try adjusting your filter criteria</p>
                    <a href="report_non_staff.php" class="btn btn-primary">
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
    // Update switch labels
    const switches = ['includeGender', 'includePhone', 'includeStatus', 'includeContract', 
                     'includeNIDA', 'includeEmploymentDate', 'includeDepartment', 'includeAddress'];
    
    switches.forEach(switchId => {
        const switchElement = document.getElementById(switchId);
        if (switchElement) {
            const label = switchElement.nextElementSibling;
            switchElement.addEventListener('change', function() {
                label.textContent = this.checked ? 'Yes' : 'No';
                // Auto-submit on column change
                document.getElementById('filterForm').submit();
            });
        }
    });
    
    // Apply filters on change for dropdowns
    const filters = ['position', 'gender', 'status', 'contract', 'department', 'has_nida'];
    filters.forEach(filterId => {
        const element = document.querySelector(`select[name="${filterId}"]`);
        if (element) {
            element.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        }
    });
    
    // Table row hover effect
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
        
        document.getElementById('filterForm').submit();
    });
    
    const columnOptionsDiv = document.querySelector('.row.mb-3 .col-md-12');
    if (columnOptionsDiv && columnOptionsDiv.querySelector('.btn') === null) {
        columnOptionsDiv.insertBefore(toggleAllBtn, columnOptionsDiv.firstChild);
    }
});

function printPreview() {
    window.print();
}
</script>

<style>
:root {
    --primary-color: <?php echo $colors['primary']; ?>;
    --primary-dark: <?php echo $colors['primary_dark']; ?>;
    --font-size-base: <?php echo $font_size_value; ?>;
    --animation-duration: <?php echo $animation_duration; ?>;
}

* {
    transition: <?php echo $animations_enabled === '1' ? 'all var(--animation-duration) ease' : 'none'; ?>;
}

body {
    font-size: var(--font-size-base);
    background: <?php echo $bg_style; ?>;
    background-size: <?php echo $bg_size; ?>;
    background-position: center;
    min-height: 100vh;
}

<?php if ($compact_mode === '1'): ?>
.card-body { padding: 0.75rem !important; }
.btn { padding: 0.5rem 1rem !important; }
.form-control, .form-select { padding: 0.375rem 0.75rem !important; }
.table td, .table th { padding: 0.5rem !important; }
<?php endif; ?>

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

.btn-lg {
    padding: 12px 30px;
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .stats-card.simple-card {
        padding: 15px;
    }
    .stats-card.simple-card h3 {
        font-size: 1.3rem;
    }
    .btn-lg {
        padding: 10px 20px;
        font-size: 1rem;
    }
    .table-responsive {
        font-size: 0.9rem;
    }
    .avatar-circle {
        width: 30px;
        height: 30px;
        margin-right: 8px;
    }
    .badge {
        white-space: normal;
        word-break: break-word;
    }
}

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