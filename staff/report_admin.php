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
    if ($role_id == 1 || $role_id == 2) { // Head Master or Second Master
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view staff members.";
    header("Location: ../404.php");
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
// Default values
$filter_role = $_GET['role'] ?? '';
$filter_gender = $_GET['gender'] ?? '';
$filter_status = $_GET['status'] ?? 'Active';
$filter_has_nida = $_GET['has_nida'] ?? '';
$include_nida = isset($_GET['include_nida']) && $_GET['include_nida'] == 1 ? true : false;
$include_gender = isset($_GET['include_gender']) && $_GET['include_gender'] == 1 ? true : false;
$include_phone = isset($_GET['include_phone']) && $_GET['include_phone'] == 1 ? true : false;
$include_status = isset($_GET['include_status']) && $_GET['include_status'] == 1 ? true : false;
$include_roles = isset($_GET['include_roles']) && $_GET['include_roles'] == 1 ? true : false;
$include_created = isset($_GET['include_created']) && $_GET['include_created'] == 1 ? true : false;

// Build SQL query based on filters
$where_conditions = [];
$params = [];
$param_types = "";

// Base query with role information
$base_sql = "SELECT a.*, 
            GROUP_CONCAT(DISTINCT ar.role_name ORDER BY ara.is_primary DESC, ar.role_name SEPARATOR ', ') as roles,
            GROUP_CONCAT(DISTINCT CASE WHEN ara.is_primary = 1 THEN ar.role_name END) as primary_role
            FROM admins a
            LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
            LEFT JOIN admin_roles ar ON ara.role_id = ar.id";

if (!empty($filter_role)) {
    $where_conditions[] = "ar.role_name = ?";
    $params[] = $filter_role;
    $param_types .= "s";
}

if (!empty($filter_gender)) {
    $where_conditions[] = "a.sex = ?";
    $params[] = $filter_gender;
    $param_types .= "s";
}

if (!empty($filter_status)) {
    if ($filter_status == 'Active') {
        $where_conditions[] = "a.status = 1";
    } else if ($filter_status == 'Inactive') {
        $where_conditions[] = "a.status = 0";
    }
}

if ($filter_has_nida == 'yes') {
    $where_conditions[] = "a.nida IS NOT NULL AND a.nida != ''";
} else if ($filter_has_nida == 'no') {
    $where_conditions[] = "(a.nida IS NULL OR a.nida = '')";
}

// Add WHERE clause if conditions exist
if (count($where_conditions) > 0) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
} else {
    $where_clause = "";
}

// Complete SQL with GROUP BY and ORDER BY
$sql = "$base_sql $where_clause GROUP BY a.id ORDER BY a.status DESC, a.first_name, a.last_name";

// Get filtered admins
$admins = [];
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
        $admins[] = $row;
    }
}

$total_admins = count($admins);

// Get all available roles for filter dropdown
$roles_sql = "SELECT DISTINCT role_name FROM admin_roles ORDER BY role_name";
$roles_result = mysqli_query($conn, $roles_sql);
$all_roles = [];
while ($row = mysqli_fetch_assoc($roles_result)) {
    $all_roles[] = $row['role_name'];
}

// Get statistics for each role
$role_stats_sql = "SELECT 
    ar.role_name,
    COUNT(DISTINCT a.id) as total,
    SUM(CASE WHEN a.sex = 'Male' THEN 1 ELSE 0 END) as males,
    SUM(CASE WHEN a.sex = 'Female' THEN 1 ELSE 0 END) as females,
    SUM(CASE WHEN a.status = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN a.status = 0 THEN 1 ELSE 0 END) as inactive
    FROM admins a
    JOIN admin_role_assignments ara ON a.id = ara.admin_id
    JOIN admin_roles ar ON ara.role_id = ar.id
    GROUP BY ar.role_name
    ORDER BY ar.role_name";
    
$role_stats_result = mysqli_query($conn, $role_stats_sql);
$role_stats = [];
while ($row = mysqli_fetch_assoc($role_stats_result)) {
    $role_stats[$row['role_name']] = $row;
}

// Handle PDF generation
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once('../tcpdf/tcpdf.php');
    
    class AdminPDF extends TCPDF {
        // Page header
        public function Header() {
            // Logo
            $logo_path = '../muyovozi.png';
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 10, 10, 20, 20, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
                $this->SetFont('helvetica', 'B', 16);
                $this->Cell(0, 0, 'MUYOVOZI HIGH SCHOOL', 0, 1, 'C');
                $this->SetFont('helvetica', '', 10);
                $this->Cell(0, 0, 'Staff/Teachers Report', 0, 1, 'C');
                $this->SetY(35);
            } else {
                $this->SetFont('helvetica', 'B', 16);
                $this->Cell(0, 0, 'MUYOVOZI HIGH SCHOOL', 0, 1, 'C');
                $this->SetFont('helvetica', '', 10);
                $this->Cell(0, 0, 'Staff/Teachers Report', 0, 1, 'C');
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
    $pdf = new AdminPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Muyovozi High School');
    $pdf->SetAuthor('Administrator');
    $pdf->SetTitle('Staff Report');
    $pdf->SetSubject('Staff/Teachers List');
    $pdf->SetKeywords('Staff, Teachers, Report, Muyovozi');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'MUYOVOZI HIGH SCHOOL', 'Staff/Teachers Report');
    
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
    $pdf->Cell(0, 10, 'STAFF/TEACHERS DETAILS', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Filter summary
    $pdf->SetFont('helvetica', '', 10);
    $filter_text = "Filters: ";
    if (!empty($filter_role)) $filter_text .= "Role: $filter_role | ";
    if (!empty($filter_gender)) $filter_text .= "Gender: $filter_gender | ";
    if (!empty($filter_status)) $filter_text .= "Status: $filter_status | ";
    if (!empty($filter_has_nida)) $filter_text .= "NIDA: " . ($filter_has_nida == 'yes' ? 'Has NIDA' : 'No NIDA') . " | ";
    $filter_text .= "Total Staff: $total_admins";
    $pdf->Cell(0, 10, $filter_text, 0, 1);
    $pdf->Ln(3);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 9);
    $header = array('S/N', 'Full Name', 'Email');
    
    // Dynamically add columns based on inclusion options
    $col_widths = [12, 50, 40];
    $column_count = 3;
    
    if ($include_roles) {
        $header[] = 'Roles';
        $col_widths[] = 30;
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
    
    if ($include_created) {
        $header[] = 'Created';
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
    $pdf->SetFont('helvetica', '', 9);
    
    // Table content
    $fill = false;
    $sn = 1;
    
    foreach($admins as $admin) {
        // Alternate row background
        if($fill) {
            $pdf->SetFillColor(240, 248, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell($col_widths[0], 8, $sn, 1, 0, 'C', $fill);
        
        // Full Name
        $fullName = $admin['first_name'];
        if (!empty($admin['middle_name'])) {
            $fullName .= ' ' . $admin['middle_name'];
        }
        $fullName .= ' ' . $admin['last_name'];
        $pdf->Cell($col_widths[1], 8, $fullName, 1, 0, 'L', $fill);
        
        // Email
        $pdf->Cell($col_widths[2], 8, $admin['email'], 1, 0, 'L', $fill);
        
        $col_index = 3;
        
        if ($include_roles) {
            $roles = explode(', ', $admin['roles']);
            $role_display = count($roles) > 2 ? implode(', ', array_slice($roles, 0, 2)) . '...' : $admin['roles'];
            $pdf->Cell($col_widths[$col_index], 8, $role_display, 1, 0, 'L', $fill);
            $col_index++;
        }
        
        if ($include_gender) {
            $pdf->Cell($col_widths[$col_index], 8, $admin['sex'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_status) {
            $pdf->Cell($col_widths[$col_index], 8, $admin['status'] ? 'Active' : 'Inactive', 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_phone) {
            $pdf->Cell($col_widths[$col_index], 8, $admin['phone_number'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_nida) {
            $pdf->Cell($col_widths[$col_index], 8, $admin['nida'] ?: 'N/A', 1, 0, 'C', $fill);
            $col_index++;
        }
        
        if ($include_created) {
            $pdf->Cell($col_widths[$col_index], 8, date('Y-m-d', strtotime($admin['created_at'])), 1, 0, 'C', $fill);
            $col_index++;
        }
        
        $pdf->Ln();
        $fill = !$fill;
        $sn++;
    }
    
    // Add role statistics section
    if (!empty($role_stats)) {
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 10, 'ROLE STATISTICS', 0, 1);
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetFillColor(59, 157, 179, 10);
        
        $role_cols = ['Role', 'Total', 'Male', 'Female', 'Active', 'Inactive'];
        $role_widths = [40, 20, 20, 20, 20, 20];
        
        // Role header
        $pdf->SetFillColor(59, 157, 179);
        $pdf->SetTextColor(255);
        for($i = 0; $i < count($role_cols); $i++) {
            $pdf->Cell($role_widths[$i], 8, $role_cols[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();
        
        // Role data
        $pdf->SetTextColor(0);
        $role_fill = false;
        
        foreach($role_stats as $role => $stats) {
            if($role_fill) {
                $pdf->SetFillColor(240, 248, 250);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $pdf->Cell($role_widths[0], 8, $role, 1, 0, 'C', $role_fill);
            $pdf->Cell($role_widths[1], 8, $stats['total'], 1, 0, 'C', $role_fill);
            $pdf->Cell($role_widths[2], 8, $stats['males'], 1, 0, 'C', $role_fill);
            $pdf->Cell($role_widths[3], 8, $stats['females'], 1, 0, 'C', $role_fill);
            $pdf->Cell($role_widths[4], 8, $stats['active'], 1, 0, 'C', $role_fill);
            $pdf->Cell($role_widths[5], 8, $stats['inactive'], 1, 0, 'C', $role_fill);
            $pdf->Ln();
            $role_fill = !$role_fill;
        }
    }
    
    // Output PDF
    $pdf->Output('staff_report_' . date('Y-m-d') . '.pdf', 'D');
    exit();
}

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="staff_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    $colspan = 3; // S/N, Full Name, Email are always included
    if ($include_roles) $colspan++;
    if ($include_gender) $colspan++;
    if ($include_status) $colspan++;
    if ($include_phone) $colspan++;
    if ($include_nida) $colspan++;
    if ($include_created) $colspan++;
    
    echo '<table border="1">
        <tr><th colspan="' . $colspan . '">MUYOVOZI HIGH SCHOOL - STAFF/TEACHERS REPORT</th></tr>
        <tr>
            <th>S/N</th>
            <th>Full Name</th>
            <th>Email</th>';
    
    if ($include_roles) {
        echo '<th>Roles</th>';
    }
    
    if ($include_gender) {
        echo '<th>Gender</th>';
    }
    
    if ($include_status) {
        echo '<th>Status</th>';
    }
    
    if ($include_phone) {
        echo '<th>Phone</th>';
    }
    
    if ($include_nida) {
        echo '<th>NIDA</th>';
    }
    
    if ($include_created) {
        echo '<th>Created</th>';
    }
    
    echo '</tr>';
    
    $sn = 1;
    foreach($admins as $admin) {
        echo '<tr>';
        echo '<td>' . $sn . '</td>';
        
        // Full Name
        $fullName = $admin['first_name'];
        if (!empty($admin['middle_name'])) {
            $fullName .= ' ' . $admin['middle_name'];
        }
        $fullName .= ' ' . $admin['last_name'];
        echo '<td>' . $fullName . '</td>';
        
        echo '<td>' . $admin['email'] . '</td>';
        
        if ($include_roles) {
            echo '<td>' . $admin['roles'] . '</td>';
        }
        
        if ($include_gender) {
            echo '<td>' . $admin['sex'] . '</td>';
        }
        
        if ($include_status) {
            echo '<td>' . ($admin['status'] ? 'Active' : 'Inactive') . '</td>';
        }
        
        if ($include_phone) {
            echo '<td>' . $admin['phone_number'] . '</td>';
        }
        
        if ($include_nida) {
            echo '<td>' . ($admin['nida'] ?: 'N/A') . '</td>';
        }
        
        if ($include_created) {
            echo '<td>' . date('Y-m-d', strtotime($admin['created_at'])) . '</td>';
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
            <h2 class="page-title">Staff/Teachers Report Generator</h2>
            <div>
                <a href="admins.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Staff Management
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
                <form method="GET" action="report_admin.php" id="filterForm">
                    <div class="row">
                        <!-- Role Filter -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" id="roleFilter">
                                <option value="">All Roles</option>
                                <?php foreach($all_roles as $role): ?>
                                <option value="<?php echo htmlspecialchars($role); ?>" 
                                    <?php echo $filter_role == $role ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role); ?>
                                </option>
                                <?php endforeach; ?>
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
                        
                        <!-- NIDA Filter -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Has NIDA?</label>
                            <select name="has_nida" class="form-select" id="nidaFilter">
                                <option value="">All</option>
                                <option value="yes" <?php echo $filter_has_nida == 'yes' ? 'selected' : ''; ?>>Has NIDA</option>
                                <option value="no" <?php echo $filter_has_nida == 'no' ? 'selected' : ''; ?>>No NIDA</option>
                            </select>
                        </div>
                        
                        <!-- Include NIDA -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Include NIDA in report?</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="include_nida" value="1" 
                                       id="includeNIDA" <?php echo $include_nida ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="includeNIDA">
                                    <?php echo $include_nida ? 'Yes' : 'No'; ?>
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
                                        <input class="form-check-input" type="checkbox" name="include_phone" value="1" 
                                               id="includePhone" <?php echo $include_phone ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includePhone">
                                            Include Phone
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
                                        <input class="form-check-input" type="checkbox" name="include_roles" value="1" 
                                               id="includeRoles" <?php echo $include_roles ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeRoles">
                                            Include Roles
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_created" value="1" 
                                               id="includeCreated" <?php echo $include_created ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeCreated">
                                            Include Created Date
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
                                    <span class="badge bg-info">Found: <?php echo $total_admins; ?> staff/teachers</span>
                                    <span class="badge bg-secondary ms-2">Ordered by Name</span>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search me-2"></i>Apply Filters
                                    </button>
                                    <a href="report_admin.php" class="btn btn-outline-secondary">
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
            // Get overall statistics
            $stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN sex = 'Male' THEN 1 ELSE 0 END) as males,
                SUM(CASE WHEN sex = 'Female' THEN 1 ELSE 0 END) as females,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN nida IS NOT NULL AND nida != '' THEN 1 ELSE 0 END) as has_nida
                FROM admins";
            
            $stats_result = mysqli_query($conn, $stats_sql);
            $overall_stats = mysqli_fetch_assoc($stats_result);
            
            // Get role count
            $role_count_sql = "SELECT COUNT(DISTINCT ar.role_name) as role_count 
                              FROM admin_roles ar
                              JOIN admin_role_assignments ara ON ar.id = ara.role_id";
            $role_count_result = mysqli_query($conn, $role_count_sql);
            $role_count = mysqli_fetch_assoc($role_count_result)['role_count'];
            ?>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-users" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $overall_stats['total'] ?? 0; ?></h3>
                    <p>Total Staff</p>
                </div>
            </div>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-user-tag" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $role_count; ?></h3>
                    <p>Unique Roles</p>
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
                        <i class="fas fa-id-card" style="color: #3B9DB3;"></i>
                    </div>
                    <h3 style="color: #ffc107;"><?php echo $overall_stats['has_nida'] ?? 0; ?></h3>
                    <p>Have NIDA</p>
                </div>
            </div>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-filter" style="color: #3B9DB3;"></i>
                    </div>
                    <h3 style="color: #3B9DB3;"><?php echo $total_admins; ?></h3>
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
                            <p class="text-muted">Generate professional PDF report with logo</p>
                            <?php
                            $export_url = "report_admin.php?" . http_build_query([
                                'role' => $filter_role,
                                'gender' => $filter_gender,
                                'status' => $filter_status == 'Both' ? '' : $filter_status,
                                'has_nida' => $filter_has_nida,
                                'include_nida' => $include_nida ? 1 : 0,
                                'include_gender' => $include_gender ? 1 : 0,
                                'include_phone' => $include_phone ? 1 : 0,
                                'include_status' => $include_status ? 1 : 0,
                                'include_roles' => $include_roles ? 1 : 0,
                                'include_created' => $include_created ? 1 : 0,
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
                            $export_excel_url = "report_admin.php?" . http_build_query([
                                'role' => $filter_role,
                                'gender' => $filter_gender,
                                'status' => $filter_status == 'Both' ? '' : $filter_status,
                                'has_nida' => $filter_has_nida,
                                'include_nida' => $include_nida ? 1 : 0,
                                'include_gender' => $include_gender ? 1 : 0,
                                'include_phone' => $include_phone ? 1 : 0,
                                'include_status' => $include_status ? 1 : 0,
                                'include_roles' => $include_roles ? 1 : 0,
                                'include_created' => $include_created ? 1 : 0,
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
                        <li>Staff are ordered by name in alphabetical order</li>
                        <li>Only selected columns will be included in the export</li>
                        <li>N/A will be displayed for staff without NIDA numbers</li>
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
                            <?php echo $total_admins; ?> staff/teachers
                        </span>
                        <span class="badge bg-info ms-2">
                            <i class="fas fa-sort-alpha-up me-1"></i>Ordered by Name
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($total_admins > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="previewTable">
                        <thead class="table-light">
                            <tr>
                                <th>S/N</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <?php if ($include_roles): ?>
                                <th>Roles</th>
                                <?php endif; ?>
                                <?php if ($include_gender): ?>
                                <th>Gender</th>
                                <?php endif; ?>
                                <?php if ($include_status): ?>
                                <th>Status</th>
                                <?php endif; ?>
                                <?php if ($include_phone): ?>
                                <th>Phone</th>
                                <?php endif; ?>
                                <?php if ($include_nida): ?>
                                <th>NIDA</th>
                                <?php endif; ?>
                                <?php if ($include_created): ?>
                                <th>Created</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($admins as $index => $admin): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3">
                                            <?php if ($admin['sex'] == 'Male'): ?>
                                                <i class="fas fa-male text-primary"></i>
                                            <?php else: ?>
                                                <i class="fas fa-female" style="color: #e83e8c;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong>
                                                <?php 
                                                $fullName = htmlspecialchars($admin['first_name']);
                                                if (!empty($admin['middle_name'])) {
                                                    $fullName .= ' ' . htmlspecialchars($admin['middle_name']);
                                                }
                                                $fullName .= ' ' . htmlspecialchars($admin['last_name']);
                                                echo $fullName;
                                                ?>
                                            </strong>
                                            <?php if (!empty($admin['nida'])): ?>
                                            <div class="small text-muted">
                                                NIDA: <?php echo htmlspecialchars($admin['nida']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                
                                <?php if ($include_roles): ?>
                                <td>
                                    <?php 
                                    $roles = explode(', ', $admin['roles']);
                                    foreach ($roles as $role):
                                        if (!empty($role)):
                                            $isPrimary = ($admin['primary_role'] == $role);
                                    ?>
                                        <span class="badge <?php echo $isPrimary ? 'bg-primary' : 'bg-secondary'; ?> me-1 mb-1">
                                            <?php echo htmlspecialchars($role); ?>
                                            <?php if ($isPrimary): ?>
                                                <i class="fas fa-star ms-1" title="Primary Role"></i>
                                            <?php endif; ?>
                                        </span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </td>
                                <?php endif; ?>
                                
                                <?php if ($include_gender): ?>
                                <td>
                                    <span class="badge <?php echo $admin['sex'] == 'Male' ? 'bg-info' : 'bg-pink'; ?>">
                                        <?php echo htmlspecialchars($admin['sex']); ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                
                                <?php if ($include_status): ?>
                                <td>
                                    <span class="badge <?php echo $admin['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $admin['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                
                                <?php if ($include_phone): ?>
                                <td><?php echo htmlspecialchars($admin['phone_number']); ?></td>
                                <?php endif; ?>
                                
                                <?php if ($include_nida): ?>
                                <td>
                                    <?php if (!empty($admin['nida'])): ?>
                                        <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($admin['nida']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                
                                <?php if ($include_created): ?>
                                <td><?php echo date('Y-m-d', strtotime($admin['created_at'])); ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                    <h4>No staff/teachers found</h4>
                    <p class="text-muted">Try adjusting your filter criteria</p>
                    <a href="report_admin.php" class="btn btn-primary">
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
    const switches = ['includeNIDA', 'includeGender', 'includePhone', 'includeStatus', 'includeRoles', 'includeCreated'];
    
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
    const filters = ['roleFilter', 'genderFilter', 'statusFilter', 'nidaFilter'];
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
            <title>Staff/Teachers Report - Muyovozi High School</title>
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
                <p>Staff/Teachers Report - Generated on <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
            
            <div class="stats no-print">
                <div class="stat-box">
                    <strong>Total Staff:</strong><br>
                    <?php echo $total_admins; ?>
                </div>
                <div class="stat-box">
                    <strong>Role:</strong><br>
                    <?php echo $filter_role ?: 'All'; ?>
                </div>
                <div class="stat-box">
                    <strong>Gender:</strong><br>
                    <?php echo $filter_gender ?: 'All'; ?>
                </div>
                <div class="stat-box">
                    <strong>Status:</strong><br>
                    <?php echo $filter_status; ?>
                </div>
            </div>
            
            <?php 
            echo '<table>';
            echo '<thead><tr>
                    <th>S/N</th>
                    <th>Full Name</th>
                    <th>Email</th>';
            
            if ($include_roles) echo '<th>Roles</th>';
            if ($include_gender) echo '<th>Gender</th>';
            if ($include_status) echo '<th>Status</th>';
            if ($include_phone) echo '<th>Phone</th>';
            if ($include_nida) echo '<th>NIDA</th>';
            if ($include_created) echo '<th>Created</th>';
            
            echo '</tr></thead><tbody>';
            
            foreach($admins as $index => $admin) {
                echo '<tr>';
                echo '<td>' . ($index + 1) . '</td>';
                
                // Full Name
                $fullName = $admin['first_name'];
                if (!empty($admin['middle_name'])) {
                    $fullName .= ' ' . $admin['middle_name'];
                }
                $fullName .= ' ' . $admin['last_name'];
                echo '<td>' . $fullName . '</td>';
                
                echo '<td>' . $admin['email'] . '</td>';
                
                if ($include_roles) echo '<td>' . $admin['roles'] . '</td>';
                if ($include_gender) echo '<td>' . $admin['sex'] . '</td>';
                if ($include_status) echo '<td>' . ($admin['status'] ? 'Active' : 'Inactive') . '</td>';
                if ($include_phone) echo '<td>' . $admin['phone_number'] . '</td>';
                if ($include_nida) echo '<td>' . ($admin['nida'] ?: 'N/A') . '</td>';
                if ($include_created) echo '<td>' . date('Y-m-d', strtotime($admin['created_at'])) . '</td>';
                
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
    
    /* Make badges wrap properly */
    .badge {
        white-space: normal;
        word-break: break-word;
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