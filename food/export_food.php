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
    if ($role_id == 1 || $role_id == 2 || $role_id == 11) { // Head Master or Second Master
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
// Default values
$filter_status = $_GET['status'] ?? '';
$filter_unit = $_GET['unit'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$show_history = isset($_GET['show_history']) && $_GET['show_history'] == 1 ? true : false;
$include_description = isset($_GET['include_description']) && $_GET['include_description'] == 1 ? true : false;
$include_status = isset($_GET['include_status']) && $_GET['include_status'] == 1 ? true : false;
$include_dates = isset($_GET['include_dates']) && $_GET['include_dates'] == 1 ? true : false;

// Build SQL query based on filters
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if (!empty($filter_status)) {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

if (!empty($filter_unit)) {
    $where_conditions[] = "unit = ?";
    $params[] = $filter_unit;
    $param_types .= "s";
}

if (!empty($date_from)) {
    $where_conditions[] = "date_added >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "date_added <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get filtered food items
$sql = "SELECT * FROM food_stock WHERE $where_clause ORDER BY 
        CASE status 
            WHEN 'out_of_stock' THEN 1
            WHEN 'low' THEN 2
            ELSE 3
        END,
        item_name ASC";

$stmt = mysqli_prepare($conn, $sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$food_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
$total_items = count($food_items);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_items,
    SUM(quantity) as total_quantity,
    SUM(CASE WHEN status = 'low' THEN 1 ELSE 0 END) as low_stock,
    SUM(CASE WHEN status = 'out_of_stock' THEN 1 ELSE 0 END) as out_of_stock,
    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_items,
    AVG(quantity) as avg_quantity,
    MIN(quantity) as min_quantity,
    MAX(quantity) as max_quantity
    FROM food_stock WHERE $where_clause";

$stats_stmt = mysqli_prepare($conn, $stats_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stats_stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get unit distribution
$unit_stats_sql = "SELECT unit, 
                   COUNT(*) as count,
                   SUM(quantity) as total_quantity,
                   AVG(quantity) as avg_quantity,
                   SUM(CASE WHEN status = 'low' THEN 1 ELSE 0 END) as low_count,
                   SUM(CASE WHEN status = 'out_of_stock' THEN 1 ELSE 0 END) as out_count
                   FROM food_stock 
                   WHERE $where_clause
                   GROUP BY unit 
                   ORDER BY count DESC";
$unit_stmt = mysqli_prepare($conn, $unit_stats_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($unit_stmt, $param_types, ...$params);
}
mysqli_stmt_execute($unit_stmt);
$unit_result = mysqli_stmt_get_result($unit_stmt);
$unit_stats = mysqli_fetch_all($unit_result, MYSQLI_ASSOC);

// Get recent stock changes for analysis
$changes_sql = "SELECT 
                f.item_name,
                h.change_type,
                COUNT(*) as change_count,
                SUM(ABS(h.new_quantity - h.old_quantity)) as total_change,
                AVG(ABS(h.new_quantity - h.old_quantity)) as avg_change
                FROM food_stock_history h
                JOIN food_stock f ON h.food_id = f.id
                WHERE h.changed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY f.item_name, h.change_type
                ORDER BY total_change DESC
                LIMIT 10";
$changes_result = mysqli_query($conn, $changes_sql);
$recent_changes = mysqli_fetch_all($changes_result, MYSQLI_ASSOC);

// Handle PDF generation
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once('../tcpdf/tcpdf.php');
    
    class FoodPDF extends TCPDF {
        // Page header
        public function Header() {
            // Logo
            $logo_path = '../muyovozi.png';
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 10, 10, 20, 20, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
                $this->SetFont('helvetica', 'B', 16);
                $this->Cell(0, 0, 'MUYOVOZI HIGH SCHOOL', 0, 1, 'C');
                $this->SetFont('helvetica', '', 10);
                $this->Cell(0, 0, 'Food Stock Analysis Report', 0, 1, 'C');
                $this->SetY(35);
            } else {
                $this->SetFont('helvetica', 'B', 16);
                $this->Cell(0, 0, 'MUYOVOZI HIGH SCHOOL', 0, 1, 'C');
                $this->SetFont('helvetica', '', 10);
                $this->Cell(0, 0, 'Food Stock Analysis Report', 0, 1, 'C');
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
    $pdf = new FoodPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Muyovozi High School');
    $pdf->SetAuthor('Administrator');
    $pdf->SetTitle('Food Stock Analysis Report');
    $pdf->SetSubject('Food Stock Analysis');
    $pdf->SetKeywords('Food, Stock, Analysis, Report');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'MUYOVOZI HIGH SCHOOL', 'Food Stock Analysis Report');
    
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
    $pdf->Cell(0, 10, 'FOOD STOCK ANALYSIS REPORT', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Report summary
    $pdf->SetFont('helvetica', '', 10);
    $summary_text = "Report Date: " . date('Y-m-d H:i:s') . "\n";
    if (!empty($filter_status)) $summary_text .= "Status: " . ucfirst($filter_status) . " | ";
    if (!empty($filter_unit)) $summary_text .= "Unit: " . $filter_unit . " | ";
    if (!empty($date_from)) $summary_text .= "From: " . $date_from . " | ";
    if (!empty($date_to)) $summary_text .= "To: " . $date_to . " | ";
    $summary_text .= "Total Items: " . $total_items;
    $pdf->MultiCell(0, 0, $summary_text, 0, 'L');
    $pdf->Ln(5);
    
    // Statistics Section
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'STATISTICS SUMMARY', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    
    $stat_cols = ['Metric', 'Value'];
    $stat_widths = [80, 40];
    
    // Statistics header
    $pdf->SetFillColor(59, 157, 179);
    $pdf->SetTextColor(255);
    for($i = 0; $i < count($stat_cols); $i++) {
        $pdf->Cell($stat_widths[$i], 8, $stat_cols[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Statistics data
    $pdf->SetTextColor(0);
    $stat_fill = false;
    
    $stat_data = [
        ['Total Items', $stats['total_items'] ?? 0],
        ['Total Quantity', number_format($stats['total_quantity'] ?? 0, 2)],
        ['Available Items', $stats['available_items'] ?? 0],
        ['Low Stock Items', $stats['low_stock'] ?? 0],
        ['Out of Stock', $stats['out_of_stock'] ?? 0],
        ['Average Quantity', number_format($stats['avg_quantity'] ?? 0, 2)],
        ['Minimum Quantity', number_format($stats['min_quantity'] ?? 0, 2)],
        ['Maximum Quantity', number_format($stats['max_quantity'] ?? 0, 2)]
    ];
    
    foreach($stat_data as $data) {
        if($stat_fill) {
            $pdf->SetFillColor(240, 248, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell($stat_widths[0], 8, $data[0], 1, 0, 'L', $stat_fill);
        $pdf->Cell($stat_widths[1], 8, $data[1], 1, 0, 'R', $stat_fill);
        $pdf->Ln();
        $stat_fill = !$stat_fill;
    }
    
    $pdf->Ln(10);
    
    // Food Stock Details
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'FOOD STOCK DETAILS', 0, 1);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 9);
    $header = ['S/N', 'Item Name', 'Quantity', 'Unit', 'Status'];
    
    $col_widths = [12, 60, 25, 20, 25];
    $column_count = 5;
    
    if ($include_description) {
        $header[] = 'Description';
        $col_widths[] = 40;
        $column_count++;
    }
    
    if ($include_dates) {
        $header[] = 'Date Added';
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
    
    foreach($food_items as $item) {
        // Alternate row background
        if($fill) {
            $pdf->SetFillColor(240, 248, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell($col_widths[0], 8, $sn, 1, 0, 'C', $fill);
        $pdf->Cell($col_widths[1], 8, $item['item_name'], 1, 0, 'L', $fill);
        $pdf->Cell($col_widths[2], 8, number_format($item['quantity'], 2), 1, 0, 'R', $fill);
        $pdf->Cell($col_widths[3], 8, $item['unit'], 1, 0, 'C', $fill);
        
        // Status with color
        $status_color = '';
        if ($item['status'] == 'available') $status_color = '#28a745';
        if ($item['status'] == 'low') $status_color = '#ffc107';
        if ($item['status'] == 'out_of_stock') $status_color = '#dc3545';
        
        $pdf->SetTextColor(hexdec(substr($status_color, 1, 2)), 
                          hexdec(substr($status_color, 3, 2)), 
                          hexdec(substr($status_color, 5, 2)));
        $pdf->Cell($col_widths[4], 8, ucfirst(str_replace('_', ' ', $item['status'])), 1, 0, 'C', $fill);
        $pdf->SetTextColor(0);
        
        $col_index = 5;
        
        if ($include_description) {
            $description = substr($item['description'] ?? '', 0, 30);
            if (strlen($item['description'] ?? '') > 30) $description .= '...';
            $pdf->Cell($col_widths[$col_index], 8, $description, 1, 0, 'L', $fill);
            $col_index++;
        }
        
        if ($include_dates) {
            $pdf->Cell($col_widths[$col_index], 8, $item['date_added'], 1, 0, 'C', $fill);
            $col_index++;
        }
        
        $pdf->Ln();
        $fill = !$fill;
        $sn++;
        
        // Check for page break
        if ($sn % 25 == 0) {
            $pdf->AddPage();
            // Re-add header
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(59, 157, 179);
            $pdf->SetTextColor(255);
            for($i = 0; $i < count($header); $i++) {
                $pdf->Cell($col_widths[$i], 8, $header[$i], 1, 0, 'C', 1);
            }
            $pdf->Ln();
            $pdf->SetTextColor(0);
            $pdf->SetFont('helvetica', '', 9);
        }
    }
    
    // Add unit distribution section if there's space
    if (count($unit_stats) > 0) {
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'UNIT DISTRIBUTION ANALYSIS', 0, 1);
        
        $pdf->SetFont('helvetica', '', 9);
        $unit_cols = ['Unit', 'Items', 'Total Qty', 'Avg Qty', 'Low', 'Out'];
        $unit_widths = [25, 20, 30, 25, 20, 20];
        
        // Unit header
        $pdf->SetFillColor(59, 157, 179);
        $pdf->SetTextColor(255);
        for($i = 0; $i < count($unit_cols); $i++) {
            $pdf->Cell($unit_widths[$i], 8, $unit_cols[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();
        
        // Unit data
        $pdf->SetTextColor(0);
        $unit_fill = false;
        
        foreach($unit_stats as $unit) {
            if($unit_fill) {
                $pdf->SetFillColor(240, 248, 250);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $pdf->Cell($unit_widths[0], 8, $unit['unit'], 1, 0, 'C', $unit_fill);
            $pdf->Cell($unit_widths[1], 8, $unit['count'], 1, 0, 'C', $unit_fill);
            $pdf->Cell($unit_widths[2], 8, number_format($unit['total_quantity'], 2), 1, 0, 'R', $unit_fill);
            $pdf->Cell($unit_widths[3], 8, number_format($unit['avg_quantity'], 2), 1, 0, 'R', $unit_fill);
            $pdf->Cell($unit_widths[4], 8, $unit['low_count'], 1, 0, 'C', $unit_fill);
            $pdf->Cell($unit_widths[5], 8, $unit['out_count'], 1, 0, 'C', $unit_fill);
            $pdf->Ln();
            $unit_fill = !$unit_fill;
        }
    }
    
    // Add stock movement analysis
    if (count($recent_changes) > 0) {
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'RECENT STOCK MOVEMENT (LAST 30 DAYS)', 0, 1);
        
        $pdf->SetFont('helvetica', '', 9);
        $move_cols = ['Item', 'Type', 'Changes', 'Total Qty', 'Avg Qty'];
        $move_widths = [60, 25, 25, 30, 30];
        
        // Movement header
        $pdf->SetFillColor(59, 157, 179);
        $pdf->SetTextColor(255);
        for($i = 0; $i < count($move_cols); $i++) {
            $pdf->Cell($move_widths[$i], 8, $move_cols[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();
        
        // Movement data
        $pdf->SetTextColor(0);
        $move_fill = false;
        
        foreach($recent_changes as $change) {
            if($move_fill) {
                $pdf->SetFillColor(240, 248, 250);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $pdf->Cell($move_widths[0], 8, $change['item_name'], 1, 0, 'L', $move_fill);
            $pdf->Cell($move_widths[1], 8, ucfirst($change['change_type']), 1, 0, 'C', $move_fill);
            $pdf->Cell($move_widths[2], 8, $change['change_count'], 1, 0, 'C', $move_fill);
            $pdf->Cell($move_widths[3], 8, number_format($change['total_change'], 2), 1, 0, 'R', $move_fill);
            $pdf->Cell($move_widths[4], 8, number_format($change['avg_change'], 2), 1, 0, 'R', $move_fill);
            $pdf->Ln();
            $move_fill = !$move_fill;
        }
    }
    
    // Add recommendations
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'RECOMMENDATIONS & ANALYSIS', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $recommendations = [];
    
    if (($stats['low_stock'] ?? 0) > 0) {
        $recommendations[] = "• " . ($stats['low_stock'] ?? 0) . " items are low on stock and need replenishment.";
    }
    
    if (($stats['out_of_stock'] ?? 0) > 0) {
        $recommendations[] = "• " . ($stats['out_of_stock'] ?? 0) . " items are out of stock and require urgent attention.";
    }
    
    if (count($recent_changes) > 0) {
        $recommendations[] = "• Review stock movement patterns for frequently adjusted items.";
    }
    
    if (empty($recommendations)) {
        $recommendations[] = "• Stock levels are generally good. Continue regular monitoring.";
    }
    
    $recommendations[] = "• Consider setting up automated reorder alerts for low stock items.";
    $recommendations[] = "• Regularly review and update stock quantities based on consumption patterns.";
    $recommendations[] = "• Maintain optimal stock levels to avoid both shortages and overstocking.";
    
    foreach($recommendations as $rec) {
        $pdf->MultiCell(0, 0, $rec, 0, 'L');
        $pdf->Ln(2);
    }
    
    // Output PDF
    $pdf->Output('food_stock_analysis_' . date('Y-m-d') . '.pdf', 'D');
    exit();
}

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="food_stock_analysis_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    $colspan = 5; // S/N, Item Name, Quantity, Unit, Status are always included
    if ($include_description) $colspan++;
    if ($include_dates) $colspan++;
    
    echo '<table border="1">';
    echo '<tr><th colspan="' . $colspan . '">MUYOVOZI HIGH SCHOOL - FOOD STOCK ANALYSIS REPORT</th></tr>';
    echo '<tr><td colspan="' . $colspan . '"><strong>Report Date:</strong> ' . date('Y-m-d H:i:s') . '</td></tr>';
    
    if (!empty($filter_status)) {
        echo '<tr><td colspan="' . $colspan . '"><strong>Status Filter:</strong> ' . ucfirst($filter_status) . '</td></tr>';
    }
    
    if (!empty($filter_unit)) {
        echo '<tr><td colspan="' . $colspan . '"><strong>Unit Filter:</strong> ' . $filter_unit . '</td></tr>';
    }
    
    echo '<tr><td colspan="' . $colspan . '"><strong>Total Items:</strong> ' . $total_items . '</td></tr>';
    
    echo '<tr>
        <th>S/N</th>
        <th>Item Name</th>
        <th>Quantity</th>
        <th>Unit</th>
        <th>Status</th>';
    
    if ($include_description) {
        echo '<th>Description</th>';
    }
    
    if ($include_dates) {
        echo '<th>Date Added</th>';
    }
    
    echo '</tr>';
    
    $sn = 1;
    foreach($food_items as $item) {
        echo '<tr>';
        echo '<td>' . $sn . '</td>';
        echo '<td>' . $item['item_name'] . '</td>';
        echo '<td>' . number_format($item['quantity'], 2) . '</td>';
        echo '<td>' . $item['unit'] . '</td>';
        echo '<td>' . ucfirst(str_replace('_', ' ', $item['status'])) . '</td>';
        
        if ($include_description) {
            echo '<td>' . ($item['description'] ?? '') . '</td>';
        }
        
        if ($include_dates) {
            echo '<td>' . $item['date_added'] . '</td>';
        }
        
        echo '</tr>';
        $sn++;
    }
    
    // Add statistics
    echo '<tr><td colspan="' . $colspan . '"></td></tr>';
    echo '<tr><th colspan="' . $colspan . '">STATISTICS SUMMARY</th></tr>';
    echo '<tr><td colspan="2">Total Items</td><td colspan="' . ($colspan - 2) . '">' . ($stats['total_items'] ?? 0) . '</td></tr>';
    echo '<tr><td colspan="2">Total Quantity</td><td colspan="' . ($colspan - 2) . '">' . number_format($stats['total_quantity'] ?? 0, 2) . '</td></tr>';
    echo '<tr><td colspan="2">Available Items</td><td colspan="' . ($colspan - 2) . '">' . ($stats['available_items'] ?? 0) . '</td></tr>';
    echo '<tr><td colspan="2">Low Stock Items</td><td colspan="' . ($colspan - 2) . '">' . ($stats['low_stock'] ?? 0) . '</td></tr>';
    echo '<tr><td colspan="2">Out of Stock</td><td colspan="' . ($colspan - 2) . '">' . ($stats['out_of_stock'] ?? 0) . '</td></tr>';
    
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
            <h2 class="page-title">Food Stock Analysis & Export</h2>
            <div>
                <a href="foods.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Food Stock
                </a>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card mb-4">
            <div class="card-header" >
                <h4 class="mb-0">
                    <i class="fas fa-filter me-2"></i>Filter & Analysis Options
                </h4>
            </div>
            <div class="card-body">
                <form method="GET" action="export_food.php" id="filterForm">
                    <div class="row">
                        <!-- Status Filter -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="available" <?php echo $filter_status == 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="low" <?php echo $filter_status == 'low' ? 'selected' : ''; ?>>Low Stock</option>
                                <option value="out_of_stock" <?php echo $filter_status == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                            </select>
                        </div>
                        
                        <!-- Unit Filter -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Unit</label>
                            <select name="unit" class="form-select" id="unitFilter">
                                <option value="">All Units</option>
                                <option value="kg" <?php echo $filter_unit == 'kg' ? 'selected' : ''; ?>>kg</option>
                                <option value="liters" <?php echo $filter_unit == 'liters' ? 'selected' : ''; ?>>liters</option>
                                <option value="bags" <?php echo $filter_unit == 'bags' ? 'selected' : ''; ?>>bags</option>
                                <option value="packets" <?php echo $filter_unit == 'packets' ? 'selected' : ''; ?>>packets</option>
                                <option value="pieces" <?php echo $filter_unit == 'pieces' ? 'selected' : ''; ?>>pieces</option>
                                <option value="cartons" <?php echo $filter_unit == 'cartons' ? 'selected' : ''; ?>>cartons</option>
                                <option value="boxes" <?php echo $filter_unit == 'boxes' ? 'selected' : ''; ?>>boxes</option>
                                <option value="cans" <?php echo $filter_unit == 'cans' ? 'selected' : ''; ?>>cans</option>
                                <option value="bottles" <?php echo $filter_unit == 'bottles' ? 'selected' : ''; ?>>bottles</option>
                                <option value="sacks" <?php echo $filter_unit == 'sacks' ? 'selected' : ''; ?>>sacks</option>
                            </select>
                        </div>
                        
                        <!-- Date From -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Date From</label>
                            <input type="date" name="date_from" class="form-control" id="dateFrom" value="<?php echo $date_from; ?>">
                        </div>
                        
                        <!-- Date To -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Date To</label>
                            <input type="date" name="date_to" class="form-control" id="dateTo" value="<?php echo $date_to; ?>">
                        </div>
                    </div>

                    <!-- Column Inclusion Options -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h6 class="mb-3">Select Columns to Include in Report:</h6>
                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_description" value="1" 
                                               id="includeDescription" <?php echo $include_description ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeDescription">
                                            Include Description
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_dates" value="1" 
                                               id="includeDates" <?php echo $include_dates ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="includeDates">
                                            Include Dates
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
                                        <input class="form-check-input" type="checkbox" name="show_history" value="1" 
                                               id="showHistory" <?php echo $show_history ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="showHistory">
                                            Show History
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
                                    <span class="badge bg-info">Found: <?php echo $total_items; ?> items</span>
                                    <span class="badge bg-secondary ms-2">Total Qty: <?php echo number_format($stats['total_quantity'] ?? 0, 2); ?></span>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search me-2"></i>Apply Filters
                                    </button>
                                    <a href="export_food.php" class="btn btn-outline-secondary">
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
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-utensils" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $stats['total_items'] ?? 0; ?></h3>
                    <p>Total Items</p>
                </div>
            </div>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-weight-hanging" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo number_format($stats['total_quantity'] ?? 0, 2); ?></h3>
                    <p>Total Quantity</p>
                </div>
            </div>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-check-circle" style="color: #28a745;"></i>
                    </div>
                    <h3 style="color: #28a745;"><?php echo $stats['available_items'] ?? 0; ?></h3>
                    <p>Available</p>
                </div>
            </div>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i>
                    </div>
                    <h3 style="color: #ffc107;"><?php echo $stats['low_stock'] ?? 0; ?></h3>
                    <p>Low Stock</p>
                </div>
            </div>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-times-circle" style="color: #dc3545;"></i>
                    </div>
                    <h3 style="color: #dc3545;"><?php echo $stats['out_of_stock'] ?? 0; ?></h3>
                    <p>Out of Stock</p>
                </div>
            </div>
            
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-chart-line" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo number_format($stats['avg_quantity'] ?? 0, 2); ?></h3>
                    <p>Avg Quantity</p>
                </div>
            </div>
        </div>

        <!-- Unit Distribution -->
        <?php if (count($unit_stats) > 0): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Unit Distribution
                </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Unit</th>
                                <th>Items</th>
                                <th>Total Quantity</th>
                                <th>Average Quantity</th>
                                <th>Low Stock</th>
                                <th>Out of Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($unit_stats as $unit): ?>
                            <tr>
                                <td><span class="badge bg-primary"><?php echo $unit['unit']; ?></span></td>
                                <td><?php echo $unit['count']; ?></td>
                                <td><?php echo number_format($unit['total_quantity'], 2); ?></td>
                                <td><?php echo number_format($unit['avg_quantity'], 2); ?></td>
                                <td>
                                    <?php if ($unit['low_count'] > 0): ?>
                                        <span class="badge bg-warning"><?php echo $unit['low_count']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($unit['out_count'] > 0): ?>
                                        <span class="badge bg-danger"><?php echo $unit['out_count']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Stock Movement -->
        <?php if (count($recent_changes) > 0): ?>
        <div class="card mb-4">
            <div class="card-header" >
                <h4 class="mb-0">
                    <i class="fas fa-exchange-alt me-2"></i>Recent Stock Movement (Last 30 Days)
                </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Type</th>
                                <th>Changes</th>
                                <th>Total Quantity</th>
                                <th>Average Change</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_changes as $change): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($change['item_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $change['change_type'] == 'add' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($change['change_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo $change['change_count']; ?></td>
                                <td><?php echo number_format($change['total_change'], 2); ?></td>
                                <td><?php echo number_format($change['avg_change'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Export Options Card -->
        <div class="card mb-4">
            <div class="card-header" >
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
                            <p class="text-muted">Professional PDF report with analysis and recommendations</p>
                            <?php
                            $export_url = "export_food.php?" . http_build_query([
                                'status' => $filter_status,
                                'unit' => $filter_unit,
                                'date_from' => $date_from,
                                'date_to' => $date_to,
                                'include_description' => $include_description ? 1 : 0,
                                'include_dates' => $include_dates ? 1 : 0,
                                'include_status' => $include_status ? 1 : 0,
                                'show_history' => $show_history ? 1 : 0,
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
                            <p class="text-muted">Excel spreadsheet with all data for analysis</p>
                            <?php
                            $export_excel_url = "export_food.php?" . http_build_query([
                                'status' => $filter_status,
                                'unit' => $filter_unit,
                                'date_from' => $date_from,
                                'date_to' => $date_to,
                                'include_description' => $include_description ? 1 : 0,
                                'include_dates' => $include_dates ? 1 : 0,
                                'include_status' => $include_status ? 1 : 0,
                                'show_history' => $show_history ? 1 : 0,
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
                        <li>PDF report includes detailed analysis, statistics, and recommendations</li>
                        <li>Excel export is suitable for further data analysis and processing</li>
                        <li>Both formats include selected columns and filters</li>
                        <li>PDF report is formatted professionally with school branding</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Preview Card -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-eye me-2"></i>Report Preview
                    </h4>
                    <div>
                        <span class="badge bg-light text-dark">
                            <?php echo $total_items; ?> items
                        </span>
                        <span class="badge bg-info ms-2">
                            <i class="fas fa-balance-scale me-1"></i>Total: <?php echo number_format($stats['total_quantity'] ?? 0, 2); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($total_items > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="previewTable">
                        <thead class="table-light">
                            <tr>
                                <th>S/N</th>
                                <th>Item Name</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Status</th>
                                <?php if ($include_description): ?>
                                <th>Description</th>
                                <?php endif; ?>
                                <?php if ($include_dates): ?>
                                <th>Date Added</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($food_items as $index => $item): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-utensils text-primary"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark"><?php echo number_format($item['quantity'], 2); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                <td>
                                    <?php 
                                    $status_color = '';
                                    if ($item['status'] == 'available') $status_color = 'success';
                                    if ($item['status'] == 'low') $status_color = 'warning';
                                    if ($item['status'] == 'out_of_stock') $status_color = 'danger';
                                    ?>
                                    <span class="badge bg-<?php echo $status_color; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                    </span>
                                </td>
                                <?php if ($include_description): ?>
                                <td>
                                    <?php if (!empty($item['description'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars(substr($item['description'], 0, 50)); ?><?php echo strlen($item['description']) > 50 ? '...' : ''; ?></small>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <?php if ($include_dates): ?>
                                <td><?php echo date('d/m/Y', strtotime($item['date_added'])); ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <h4>No food items found</h4>
                    <p class="text-muted">Try adjusting your filter criteria</p>
                    <a href="export_food.php" class="btn btn-primary">
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
    const switches = ['includeDescription', 'includeDates', 'includeStatus', 'showHistory'];
    
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
    const filters = ['statusFilter', 'unitFilter', 'dateFrom', 'dateTo'];
    filters.forEach(filterId => {
        const element = document.getElementById(filterId);
        if (element) {
            element.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        }
    });
    
    // Set date range defaults
    const today = new Date().toISOString().split('T')[0];
    const oneMonthAgo = new Date();
    oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
    const oneMonthAgoStr = oneMonthAgo.toISOString().split('T')[0];
    
    if (!document.getElementById('dateFrom').value) {
        document.getElementById('dateFrom').value = oneMonthAgoStr;
    }
    
    if (!document.getElementById('dateTo').value) {
        document.getElementById('dateTo').value = today;
    }
    
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
});
</script>

<style>
/* Custom styles for export page */
.card-header{
     background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);
}
.export-option {
    transition: transform 0.3s ease;
}

.export-option:hover {
    transform: translateY(-5px);
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

.table th {
    background-color: rgba(59, 157, 179, 0.1);
    border-bottom: 2px solid #3B9DB3;
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(59, 157, 179, 0.02);
}

.btn-lg {
    padding: 12px 30px;
    font-size: 1.1rem;
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
}
</style>

<?php include '../controller/footer.php'; ?>