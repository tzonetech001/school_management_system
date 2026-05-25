<?php
// candidates/maintenance.php
session_start();
require_once '../controller/db_connect.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Get student information
$student_sql = "SELECT first_name, last_name, index_number, class, combination, sex 
                FROM students WHERE id = $student_id";
$student_result = mysqli_query($conn, $student_sql);
$student = mysqli_fetch_assoc($student_result);
$student_name = $student['first_name'] . ' ' . $student['last_name'];

// Get student's assigned maintenance items
$assignments_sql = "SELECT ma.*, 
                   mi.item_code, mi.item_type, mi.description as item_description,
                   mi.location, mi.status as item_status,
                   a.first_name as assigned_by_fname, a.last_name as assigned_by_lname,
                   DATEDIFF(CURDATE(), ma.due_date) as days_overdue,
                   CASE 
                       WHEN ma.due_date IS NULL THEN NULL
                       WHEN ma.due_date < CURDATE() THEN 'Overdue'
                       WHEN ma.due_date = CURDATE() THEN 'Due Today'
                       ELSE 'On Time'
                   END as due_status
                   FROM maintenance_assignments ma
                   JOIN maintenance_items mi ON ma.item_id = mi.id
                   LEFT JOIN admins a ON ma.assigned_by = a.id
                   WHERE ma.student_id = $student_id 
                   AND ma.status = 'active'
                   ORDER BY 
                       CASE 
                           WHEN ma.due_date < CURDATE() THEN 0
                           ELSE 1
                       END,
                       ma.assigned_date DESC";

$assignments_result = mysqli_query($conn, $assignments_sql);

// Get assignment history (returned items)
$history_sql = "SELECT ma.*, 
               mi.item_code, mi.item_type, mi.description as item_description,
               a.first_name as assigned_by_fname, a.last_name as assigned_by_lname
               FROM maintenance_assignments ma
               JOIN maintenance_items mi ON ma.item_id = mi.id
               LEFT JOIN admins a ON ma.assigned_by = a.id
               WHERE ma.student_id = $student_id 
               AND ma.status = 'returned'
               ORDER BY ma.return_date DESC
               LIMIT 10";

$history_result = mysqli_query($conn, $history_sql);

// Count assignments by type
$table_count = 0;
$chair_count = 0;
$overdue_count = 0;

if (mysqli_num_rows($assignments_result) > 0) {
    mysqli_data_seek($assignments_result, 0);
    while ($item = mysqli_fetch_assoc($assignments_result)) {
        if ($item['item_type'] == 'table') $table_count++;
        if ($item['item_type'] == 'chair') $chair_count++;
        if ($item['due_date'] && strtotime($item['due_date']) < time()) $overdue_count++;
    }
    mysqli_data_seek($assignments_result, 0);
}
?>

<?php include 'header.php'; ?>
<?php include 'sidebar_student.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">
                <i class="fas fa-tools me-2" style="color: #3B9DB3;"></i>
                My Maintenance Items
            </h2>
            <span class="badge bg-info fs-6 p-3">
                <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($student_name); ?>
            </span>
        </div>

        <!-- Student Info Card -->
        <div class="card mb-4">
            <div class="card-body" style="background: white;">
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle me-3">
                                <?php if ($student['sex'] == 'Male'): ?>
                                    <i class="fas fa-male text-primary fa-2x"></i>
                                <?php else: ?>
                                    <i class="fas fa-female" style="color: #e83e8c; font-size: 2rem;"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="text-muted small">Student Name</div>
                                <strong><?php echo htmlspecialchars($student_name); ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-id-card fa-2x text-primary me-3"></i>
                            <div>
                                <div class="text-muted small">Index Number</div>
                                <strong><?php echo htmlspecialchars($student['index_number']); ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-graduation-cap fa-2x text-success me-3"></i>
                            <div>
                                <div class="text-muted small">Class / Combination</div>
                                <strong><?php echo htmlspecialchars($student['class'] . ' ' . $student['combination']); ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-calendar-alt fa-2x text-info me-3"></i>
                            <div>
                                <div class="text-muted small">Academic Year</div>
                                <strong>2025/2026</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-cube" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo mysqli_num_rows($assignments_result); ?></h3>
                    <p>Total Assigned Items</p>
                </div>
            </div>
            
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-table" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $table_count; ?></h3>
                    <p>Tables Assigned</p>
                </div>
            </div>
            
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-chair" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $chair_count; ?></h3>
                    <p>Chairs Assigned</p>
                </div>
            </div>
        </div>

        <?php if ($overdue_count > 0): ?>
        <!-- Overdue Warning -->
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Attention!</strong> You have <?php echo $overdue_count; ?> overdue item(s). Please return them as soon as possible.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Current Assignments -->
        <div class="card mb-4">
            <div class="card-header" style="background-color: #3B9DB3; color: white;">
                <h4 class="mb-0">
                    <i class="fas fa-list-check me-2"></i>
                    My Currently Assigned Items
                    <span class="badge bg-light text-dark ms-2"><?php echo mysqli_num_rows($assignments_result); ?> Items</span>
                </h4>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($assignments_result) == 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h5>No Items Assigned</h5>
                        <p class="text-muted">You don't have any maintenance items assigned to you at the moment.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Item Code</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Location</th>
                                    <th>Assigned Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Assigned By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = mysqli_fetch_assoc($assignments_result)): 
                                    $due_status_class = '';
                                    $due_status_text = '';
                                    
                                    if ($item['due_date']) {
                                        if (strtotime($item['due_date']) < time()) {
                                            $due_status_class = 'bg-danger';
                                            $due_status_text = 'Overdue';
                                        } elseif (strtotime($item['due_date']) == strtotime(date('Y-m-d'))) {
                                            $due_status_class = 'bg-warning text-dark';
                                            $due_status_text = 'Due Today';
                                        } else {
                                            $due_status_class = 'bg-success';
                                            $due_status_text = 'On Time';
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['item_code']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $item['item_type'] == 'table' ? 'bg-primary' : 'bg-info'; ?>">
                                            <i class="fas <?php echo $item['item_type'] == 'table' ? 'fa-table' : 'fa-chair'; ?> me-1"></i>
                                            <?php echo ucfirst($item['item_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($item['item_description'] ?: 'No description'); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($item['location'] ?: 'Not specified'); ?>
                                    </td>
                                    <td>
                                        <?php echo date('d M Y', strtotime($item['assigned_date'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($item['due_date']): ?>
                                            <?php echo date('d M Y', strtotime($item['due_date'])); ?>
                                            <span class="badge <?php echo $due_status_class; ?> ms-1">
                                                <?php echo $due_status_text; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No due date</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i>Active
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($item['assigned_by_fname']): ?>
                                            <?php echo htmlspecialchars($item['assigned_by_fname'] . ' ' . $item['assigned_by_lname']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">System</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assignment History -->
        <div class="card">
            <div class="card-header" style="background-color: #3B9DB3; color: white;">
                <h4 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Recent Return History
                    <span class="badge bg-light text-dark ms-2">Last 10 Items</span>
                </h4>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($history_result) == 0): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-undo-alt fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No return history found.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Item Code</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Assigned Date</th>
                                    <th>Returned Date</th>
                                    <th>Condition</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($history = mysqli_fetch_assoc($history_result)): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($history['item_code']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $history['item_type'] == 'table' ? 'bg-primary' : 'bg-info'; ?>">
                                            <?php echo ucfirst($history['item_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($history['item_description'] ?: 'No description'); ?>
                                    </td>
                                    <td>
                                        <?php echo date('d M Y', strtotime($history['assigned_date'])); ?>
                                    </td>
                                    <td>
                                        <?php echo date('d M Y', strtotime($history['return_date'])); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $condition_badge = '';
                                        switch ($history['return_condition']) {
                                            case 'good':
                                                $condition_badge = 'bg-success';
                                                break;
                                            case 'damaged':
                                                $condition_badge = 'bg-warning text-dark';
                                                break;
                                            case 'lost':
                                                $condition_badge = 'bg-danger';
                                                break;
                                            default:
                                                $condition_badge = 'bg-secondary';
                                        }
                                        ?>
                                        <span class="badge <?php echo $condition_badge; ?>">
                                            <?php echo ucfirst($history['return_condition']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($history['return_notes'] ?: '-'); ?></small>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Important Information -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header" style="background-color: #3B9DB3; color: white;">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Important Notes</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                You are responsible for the items assigned to you.
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                Return items before the due date to avoid penalties.
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-tools text-info me-2"></i>
                                Report any damage immediately to the maintenance office.
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-undo-alt text-primary me-2"></i>
                                All items must be returned when leaving school or graduating.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- <div class="col-md-6">
                <div class="card">
                    <div class="card-header" style="background-color: #3B9DB3; color: white;">
                        <h5 class="mb-0"><i class="fas fa-phone-alt me-2"></i>Need Help?</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="contact-icon me-3">
                                <i class="fas fa-user-tie fa-2x text-primary"></i>
                            </div>
                            <div>
                                <div class="fw-bold">Maintenance Officer</div>
                                <small class="text-muted">Mr. John Doe</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="contact-icon me-3">
                                <i class="fas fa-phone fa-2x text-success"></i>
                            </div>
                            <div>
                                <div class="fw-bold">Phone</div>
                                <small class="text-muted">+255 712 345 678</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="contact-icon me-3">
                                <i class="fas fa-envelope fa-2x text-info"></i>
                            </div>
                            <div>
                                <div class="fw-bold">Email</div>
                                <small class="text-muted">maintenance@muyovozi.ac.tz</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div> -->
        </div>
    </div>
</div>

<style>
.stats-card.simple-card {
    border: none;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    background: white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.stats-card.simple-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: #3B9DB3;
}

.stats-card.simple-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.stats-card.simple-card .stats-icon {
    margin-bottom: 10px;
}

.stats-card.simple-card .stats-icon i {
    font-size: 2.2rem;
}

.stats-card.simple-card h3 {
    font-size: 1.8rem;
    font-weight: bold;
    color: #333;
    margin: 10px 0;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

.stats-card.simple-card p {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
    font-weight: 500;
}

.avatar-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: rgba(59, 157, 179, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state i {
    opacity: 0.5;
}

.empty-state h5 {
    color: #666;
    margin: 15px 0 10px 0;
}

.empty-state p {
    color: #999;
    max-width: 400px;
    margin: 0 auto;
}

.table th {
    font-weight: 600;
    color: #333;
    background-color: rgba(59, 157, 179, 0.05);
    border-bottom: 2px solid rgba(59, 157, 179, 0.2);
}

.contact-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: rgba(59, 157, 179, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 768px) {
    .stats-card.simple-card {
        padding: 15px;
    }
    
    .stats-card.simple-card h3 {
        font-size: 1.5rem;
    }
    
    .avatar-circle {
        width: 40px;
        height: 40px;
    }
    
    .contact-icon {
        width: 40px;
        height: 40px;
    }
}
</style>

<?php include '../controller/footer.php'; ?>