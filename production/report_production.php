<?php
// report_production.php
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../controller/login.php");
    exit();
}

// Set headers for download
if (isset($_GET['download'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=production_report_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Header row
    fputcsv($output, [
        'ID', 'Category', 'Production Type', 'Quantity', 'Unit', 
        'Amount (TZS)', 'Date', 'Short Note', 'Created By', 'Created At'
    ]);
    
    // Get all productions
    $sql = "SELECT p.*, 
            CONCAT(a.first_name, ' ', a.last_name) as created_by_name
            FROM productions p
            LEFT JOIN admins a ON p.created_by = a.id
            ORDER BY p.production_date DESC";
    
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['id'],
            ucfirst($row['category']),
            $row['production_type'],
            $row['quantity'] ?? '',
            $row['unit'] ?? '',
            $row['amount'] ?? '',
            $row['production_date'],
            $row['short_note'] ?? '',
            $row['created_by_name'],
            $row['created_at']
        ]);
    }
    
    fclose($output);
    exit();
}

// Get summary statistics for display
$summary_sql = "SELECT 
    COUNT(*) as total_productions,
    SUM(amount) as total_value,
    AVG(amount) as avg_value,
    COUNT(DISTINCT category) as categories_count
    FROM productions";
$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result);

// Get productions by category
$category_sql = "SELECT category, 
    COUNT(*) as count,
    SUM(amount) as total_value,
    SUM(quantity) as total_quantity
    FROM productions 
    GROUP BY category 
    ORDER BY count DESC";
$category_result = mysqli_query($conn, $category_sql);
$categories = [];
while ($row = mysqli_fetch_assoc($category_result)) {
    $categories[] = $row;
}

// Get monthly summary
$monthly_sql = "SELECT 
    DATE_FORMAT(production_date, '%Y-%m') as month,
    COUNT(*) as count,
    SUM(amount) as total_value
    FROM productions 
    GROUP BY DATE_FORMAT(production_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6";
$monthly_result = mysqli_query($conn, $monthly_sql);
$monthly_data = [];
while ($row = mysqli_fetch_assoc($monthly_result)) {
    $monthly_data[] = $row;
}

?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">Production Report</h2>
            <div class="btn-group">
                <a href="report_production.php?download=1" class="btn btn-success">
                    <i class="fas fa-download me-2"></i>Export CSV
                </a>
                <a href="productions.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Productions
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-boxes" style="color: #28a745;"></i>
                    </div>
                    <h3><?php echo $summary['total_productions']; ?></h3>
                    <p>Total Productions</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-money-bill-wave" style="color: #ffc107;"></i>
                    </div>
                    <h3><?php echo number_format($summary['total_value'] ?? 0, 2); ?></h3>
                    <p>Total Value (TZS)</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-tags" style="color: #17a2b8;"></i>
                    </div>
                    <h3><?php echo $summary['categories_count']; ?></h3>
                    <p>Categories</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-chart-line" style="color: #6f42c1;"></i>
                    </div>
                    <h3><?php echo number_format($summary['avg_value'] ?? 0, 2); ?></h3>
                    <p>Average Value</p>
                </div>
            </div>
        </div>

        <!-- Report Sections -->
        <div class="row">
            <!-- Category Distribution -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header" style="background-color: #17a2b8; color: white;">
                        <h5 class="mb-0">Production by Category</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Count</th>
                                        <th>Quantity</th>
                                        <th>Value (TZS)</th>
                                        <th>Avg Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td>
                                            <span class="badge category-badge" style="background-color: <?php echo getCategoryColor($cat['category']); ?>">
                                                <?php echo ucfirst($cat['category']); ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo $cat['count']; ?></strong></td>
                                        <td><?php echo $cat['total_quantity'] ?? 0; ?></td>
                                        <td class="text-success"><?php echo number_format($cat['total_value'] ?? 0, 2); ?></td>
                                        <td class="text-info"><?php echo number_format(($cat['total_value'] ?? 0) / $cat['count'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Summary -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header" style="background-color: #6f42c1; color: white;">
                        <h5 class="mb-0">Last 6 Months Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Productions</th>
                                        <th>Value (TZS)</th>
                                        <th>Avg per Item</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthly_data as $month): ?>
                                    <tr>
                                        <td><strong><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></strong></td>
                                        <td><?php echo $month['count']; ?></td>
                                        <td class="text-success"><?php echo number_format($month['total_value'] ?? 0, 2); ?></td>
                                        <td class="text-info">
                                            <?php echo number_format(($month['total_value'] ?? 0) / $month['count'], 2); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Report -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header" style="background-color: #28a745; color: white;">
                        <h5 class="mb-0">Detailed Production Report</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="detailedReport">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Quantity</th>
                                        <th>Value (TZS)</th>
                                        <th>Notes</th>
                                        <th>Created By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "SELECT p.*, 
                                            CONCAT(a.first_name, ' ', a.last_name) as created_by_name
                                            FROM productions p
                                            LEFT JOIN admins a ON p.created_by = a.id
                                            ORDER BY p.production_date DESC";
                                    $result = mysqli_query($conn, $sql);
                                    $counter = 1;
                                    
                                    while ($row = mysqli_fetch_assoc($result)):
                                    ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['production_date'])); ?></td>
                                        <td>
                                            <span class="badge category-badge" style="background-color: <?php echo getCategoryColor($row['category']); ?>">
                                                <?php echo ucfirst($row['category']); ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($row['production_type']); ?></strong></td>
                                        <td>
                                            <?php if (!empty($row['quantity'])): ?>
                                                <?php echo $row['quantity']; ?> <?php echo $row['unit']; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-success">
                                            <?php if (!empty($row['amount'])): ?>
                                                <strong><?php echo number_format($row['amount'], 2); ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['short_note'])): ?>
                                                <span title="<?php echo htmlspecialchars($row['short_note']); ?>">
                                                    <?php echo substr(htmlspecialchars($row['short_note']), 0, 30); ?>
                                                    <?php if (strlen($row['short_note']) > 30): ?>...<?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['created_by_name']); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.category-badge {
    padding: 5px 10px;
    border-radius: 15px;
    color: white;
    font-size: 0.85rem;
}

.stats-card.simple-card {
    border: none;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    background: white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    height: 100%;
}

.stats-card.simple-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.table th {
    font-weight: 600;
    background-color: rgba(0,0,0,0.02);
}

#detailedReport tbody tr:hover {
    background-color: rgba(40, 167, 69, 0.05);
}
</style>

<script>
// Add search functionality to detailed report
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.className = 'form-control mb-3';
    searchInput.placeholder = 'Search in detailed report...';
    
    const detailedReport = document.getElementById('detailedReport');
    detailedReport.parentNode.insertBefore(searchInput, detailedReport);
    
    searchInput.addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const rows = detailedReport.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchValue) ? '' : 'none';
        });
    });
});
</script>

<?php
function getCategoryColor($category) {
    $colors = [
        'shop' => '#ffc107',
        'farm' => '#28a745',
        'beekeeping' => '#fd7e14',
        'soap' => '#17a2b8',
        'fish' => '#20c997',
        'hen' => '#e83e8c',
        'garden' => '#6f42c1'
    ];
    return $colors[$category] ?? '#6c757d';
}
?>

<?php include '../controller/footer.php'; ?>