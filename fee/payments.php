<?php
// fee/payments.php
session_start();
require_once '../controller/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Get school_id
$admin_sql = "SELECT school_id FROM admins WHERE id = ?";
$stmt = mysqli_prepare($conn, $admin_sql);
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);
$admin_result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($admin_result);
if (!$admin) {
    header("Location: ../index.php");
    exit();
}
$school_id = $admin['school_id'];

// Check if user has permission (Head Master or Bursar)
$role_sql = "SELECT COUNT(*) as count FROM admin_role_assignments ara WHERE ara.admin_id = ? AND ara.role_id IN (1, 8)";
$role_stmt = mysqli_prepare($conn, $role_sql);
mysqli_stmt_bind_param($role_stmt, "i", $admin_id);
mysqli_stmt_execute($role_stmt);
$role_result = mysqli_stmt_get_result($role_stmt);
$role_count = mysqli_fetch_assoc($role_result)['count'];

if ($role_count == 0) {
    header("Location: ../dashboard.php?error=unauthorized");
    exit();
}

// Get filter parameters
$student_filter = isset($_GET['student']) ? intval($_GET['student']) : 0;
$date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, $_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, $_GET['date_to']) : '';
$academic_year = isset($_GET['academic_year']) ? mysqli_real_escape_string($conn, $_GET['academic_year']) : '';

// Get all available academic years from fee_settings
$years_sql = "SELECT DISTINCT academic_year FROM fee_settings WHERE school_id = ? ORDER BY academic_year DESC";
$years_stmt = mysqli_prepare($conn, $years_sql);
mysqli_stmt_bind_param($years_stmt, "i", $school_id);
mysqli_stmt_execute($years_stmt);
$years_result = mysqli_stmt_get_result($years_stmt);
$academic_years = [];
while ($y = mysqli_fetch_assoc($years_result)) {
    $academic_years[] = $y['academic_year'];
}
mysqli_stmt_close($years_stmt);

// If no academic year selected, use the latest one if available
if (empty($academic_year) && !empty($academic_years)) {
    $academic_year = $academic_years[0];
}

// Get fee settings for the selected academic year (or latest)
$fee_settings = null;
if ($academic_year) {
    $fee_sql = "SELECT * FROM fee_settings WHERE school_id = ? AND academic_year = ?";
    $fee_stmt = mysqli_prepare($conn, $fee_sql);
    mysqli_stmt_bind_param($fee_stmt, "is", $school_id, $academic_year);
    mysqli_stmt_execute($fee_stmt);
    $fee_result = mysqli_stmt_get_result($fee_stmt);
    $fee_settings = mysqli_fetch_assoc($fee_result);
    mysqli_stmt_close($fee_stmt);
}
if (!$fee_settings && !empty($academic_years)) {
    // Fallback to latest
    $fee_sql = "SELECT * FROM fee_settings WHERE school_id = ? ORDER BY updated_at DESC LIMIT 1";
    $fee_stmt = mysqli_prepare($conn, $fee_sql);
    mysqli_stmt_bind_param($fee_stmt, "i", $school_id);
    mysqli_stmt_execute($fee_stmt);
    $fee_result = mysqli_stmt_get_result($fee_stmt);
    $fee_settings = mysqli_fetch_assoc($fee_result);
    mysqli_stmt_close($fee_stmt);
    if ($fee_settings) {
        $academic_year = $fee_settings['academic_year'];
    }
}

$total_fee_amount = $fee_settings ? floatval($fee_settings['total_fee']) : 0;

// Build query to get students with their total paid and balance
$query = "SELECT 
            s.id, 
            s.index_number, 
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            s.class,
            COALESCE(SUM(p.amount), 0) as total_paid
          FROM students s
          LEFT JOIN student_payments p ON s.id = p.student_id AND p.status = 'completed'
          WHERE s.school_id = ? AND s.status = 1 AND s.is_leaver = 0";
$params = [$school_id];
$types = "i";

if ($student_filter > 0) {
    $query .= " AND s.id = ?";
    $params[] = $student_filter;
    $types .= "i";
}
if ($date_from) {
    $query .= " AND (p.payment_date IS NULL OR p.payment_date >= ?)";
    $params[] = $date_from;
    $types .= "s";
}
if ($date_to) {
    $query .= " AND (p.payment_date IS NULL OR p.payment_date <= ?)";
    $params[] = $date_to;
    $types .= "s";
}

$query .= " GROUP BY s.id ORDER BY s.last_name, s.first_name";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$students_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['total_fee'] = $total_fee_amount;
    $row['balance'] = $total_fee_amount - $row['total_paid'];
    $students_data[] = $row;
}

// Get all students for dropdown filter
$students_list_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, index_number 
                      FROM students WHERE school_id = ? AND status = 1 ORDER BY last_name";
$students_list_stmt = mysqli_prepare($conn, $students_list_sql);
mysqli_stmt_bind_param($students_list_stmt, "i", $school_id);
mysqli_stmt_execute($students_list_stmt);
$students_list = mysqli_stmt_get_result($students_list_stmt);

include '../controller/header.php';
include '../controller/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-file-invoice me-2" style="color: var(--primary-color);"></i>Payment Report</h2>
                <a href="record_payment.php" class="btn btn-sm btn-primary mt-2">
                    <i class="fas fa-plus-circle me-1"></i>Record Payment
                </a>
            </div>
            <div>
                <?php if ($fee_settings): ?>
                    <span class="badge bg-success p-2">
                        Academic Year: <?php echo htmlspecialchars($academic_year); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filters -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Student</label>
                        <select name="student" class="form-select">
                            <option value="0">All Students</option>
                            <?php while($s = mysqli_fetch_assoc($students_list)): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($student_filter == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['full_name'] . ' (' . $s['index_number'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Academic Year</label>
                        <select name="academic_year" class="form-select">
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($academic_year == $year) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 me-2">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='payments.php'">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Totals -->
        <?php 
            $grand_total_fee = 0;
            $grand_total_paid = 0;
            $grand_balance = 0;
            foreach ($students_data as $s) {
                $grand_total_fee += $s['total_fee'];
                $grand_total_paid += $s['total_paid'];
                $grand_balance += $s['balance'];
            }
        ?>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Total Fee</h6>
                        <h3 class="text-primary">TZS <?php echo number_format($grand_total_fee, 0); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Total Paid</h6>
                        <h3 class="text-success">TZS <?php echo number_format($grand_total_paid, 0); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Total Balance</h6>
                        <h3 class="text-danger">TZS <?php echo number_format($grand_balance, 0); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Student</th>
                                <th>Index No</th>
                                <th>Class</th>
                                <th>Total Fee (TZS)</th>
                                <th>Paid (TZS)</th>
                                <th>Balance (TZS)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($students_data) > 0): ?>
                                <?php foreach ($students_data as $s): 
                                    $status_class = ($s['balance'] <= 0) ? 'success' : (($s['balance'] < $s['total_fee']) ? 'warning' : 'danger');
                                    $status_text = ($s['balance'] <= 0) ? 'Paid in Full' : (($s['balance'] < $s['total_fee']) ? 'Partial' : 'Not Paid');
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($s['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($s['index_number']); ?></td>
                                        <td><?php echo htmlspecialchars($s['class']); ?></td>
                                        <td><?php echo number_format($s['total_fee'], 0); ?></td>
                                        <td><?php echo number_format($s['total_paid'], 0); ?></td>
                                        <td><?php echo number_format($s['balance'], 0); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No students found matching the filters.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../controller/footer.php'; ?>

<script>
// Listen for payments update from other tabs and reload to reflect changes
window.addEventListener('storage', function(e) {
    if (e.key === 'payments_update') {
        try {
            var data = JSON.parse(e.newValue);
            // Optionally: reload only when relevant student changed; for simplicity reload all
            location.reload();
        } catch (err) {
            location.reload();
        }
    }
});
</script>