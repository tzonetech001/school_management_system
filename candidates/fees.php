<?php
// candidates/my_fees.php - Student Contributions/Fees Page
session_start();
require_once '../controller/db_connect.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Get student information
$student_sql = "SELECT * FROM students WHERE id = $student_id";
$student_result = mysqli_query($conn, $student_sql);
$student = mysqli_fetch_assoc($student_result);

// Get contribution information from student_equipment table
$contribution_sql = "SELECT 
    contribution_target,
    contribution_paid,
    contribution_balance,
    contribution_status,
    contribution_last_payment,
    updated_at
FROM student_equipment 
WHERE student_id = $student_id";

$contribution_result = mysqli_query($conn, $contribution_sql);

// If no record exists, create one
if (mysqli_num_rows($contribution_result) == 0) {
    $insert_sql = "INSERT INTO student_equipment (student_id, contribution_target, contribution_paid, contribution_balance, contribution_status) 
                   VALUES ($student_id, 80000.00, 0.00, 80000.00, 'Not Paid')";
    mysqli_query($conn, $insert_sql);
    
    // Fetch the new record
    $contribution_result = mysqli_query($conn, $contribution_sql);
}

$contribution = mysqli_fetch_assoc($contribution_result);

// Get payment history
$payments_sql = "SELECT 
    cp.*,
    a.first_name as admin_first,
    a.last_name as admin_last
FROM contribution_payments cp
LEFT JOIN admins a ON cp.received_by = a.id
WHERE cp.student_id = $student_id
ORDER BY cp.payment_date DESC, cp.created_at DESC";

$payments_result = mysqli_query($conn, $payments_sql);
$payments = [];
if ($payments_result && mysqli_num_rows($payments_result) > 0) {
    while ($row = mysqli_fetch_assoc($payments_result)) {
        $payments[] = $row;
    }
}

// Calculate progress percentage
$target = $contribution['contribution_target'] ?: 80000.00;
$paid = $contribution['contribution_paid'] ?: 0.00;
$progress_percentage = $target > 0 ? min(round(($paid / $target) * 100, 1), 100) : 0;

// Get status color and message
$status_colors = [
    'Paid' => 'success',
    'Partially Paid' => 'warning',
    'Not Paid' => 'danger'
];

$status_messages = [
    'Paid' => 'Your contribution is fully paid. Thank you!',
    'Partially Paid' => 'You have made partial payment. Please clear the balance.',
    'Not Paid' => 'No payment has been recorded yet. Please make your contribution.'
];

$status_color = $status_colors[$contribution['contribution_status']] ?? 'secondary';
$status_message = $status_messages[$contribution['contribution_status']] ?? 'Payment status unknown';

// Calculate due date (end of academic year assumption)
$current_year = date('Y');
$next_year = $current_year + 1;
$due_date = "$next_year-06-30"; // Assuming academic year ends June 30th
$days_left = ceil((strtotime($due_date) - time()) / (60 * 60 * 24));
?>

<?php include 'header.php'; ?>
<?php include 'sidebar_student.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">
                <i class="fas fa-money-bill-wave me-2" style="color: #28a745;"></i>
                My Contributions
            </h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>

        <!-- Student Info Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h5>
                                <p class="text-muted mb-1">
                                    <i class="fas fa-id-card me-2"></i>Index: <?php echo htmlspecialchars($student['index_number']); ?>
                                </p>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-graduation-cap me-2"></i><?php echo htmlspecialchars($student['class'] . ' - ' . $student['combination']); ?>
                                </p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <span class="badge bg-<?php echo $status_color; ?> p-3" style="font-size: 1rem;">
                                    <i class="fas fa-<?php echo $contribution['contribution_status'] == 'Paid' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                                    Status: <?php echo $contribution['contribution_status']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Contribution Card -->
        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card h-100">
                    <div class="card-header" style="background-color: #28a745; color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>
                            Contribution Summary
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Progress Section -->
                        <div class="text-center mb-4">
                            <h2 class="display-4 fw-bold text-primary">TZS <?php echo number_format($paid, 0); ?></h2>
                            <p class="text-muted">of TZS <?php echo number_format($target, 0); ?> target</p>
                            
                            <div class="progress mb-3" style="height: 30px;">
                                <div class="progress-bar bg-<?php echo $status_color; ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo $progress_percentage; ?>%; font-size: 14px; font-weight: bold;"
                                     aria-valuenow="<?php echo $progress_percentage; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    <?php echo $progress_percentage; ?>%
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-4">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Target Amount</small>
                                        <strong>TZS <?php echo number_format($target, 0); ?></strong>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Paid Amount</small>
                                        <strong class="text-success">TZS <?php echo number_format($paid, 0); ?></strong>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Balance</small>
                                        <strong class="<?php echo $contribution['contribution_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                            TZS <?php echo number_format($contribution['contribution_balance'], 0); ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Message -->
                        <div class="alert alert-<?php echo $status_color; ?> mt-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <?php echo $status_message; ?>
                        </div>
                        
                        <!-- Payment Info -->
                        <?php if ($contribution['contribution_last_payment']): ?>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-clock me-2"></i>
                                Last payment recorded on: 
                                <strong><?php echo date('F j, Y', strtotime($contribution['contribution_last_payment'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Due Date Info -->
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-calendar-alt me-2"></i>
                            <strong>Payment Due Date:</strong> <?php echo date('F j, Y', strtotime($due_date)); ?>
                            (<?php echo $days_left; ?> days left)
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Methods Card -->
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header" style="background-color: #17a2b8; color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i>
                            Payment Methods
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6><i class="fas fa-university me-2 text-primary"></i>Bank Transfer</h6>
                            <p class="text-muted small">
                                <strong>Bank:</strong> CRDB Bank<br>
                                <strong>Account Name:</strong> Muyovozi High School<br>
                                <strong>Account Number:</strong> No account number.<br>
                                <strong>Reference:</strong> <?php echo $student['index_number']; ?>
                            </p>
                        </div>
                        
                        <div class="mb-4">
                            <h6><i class="fas fa-mobile-alt me-2 text-success"></i>Mobile Money</h6>
                            <p class="text-muted small">
                                <strong>Tigo Pesa:</strong> *150*01#<br>
                                <strong>M-Pesa:</strong> *150*00#<br>
                                <strong>Airtel Money:</strong> *150*60#<br>
                                <strong>Paybill Number:</strong> 543210
                            </p>
                        </div>
                        
                        <div class="mb-4">
                            <h6><i class="fas fa-cash-register me-2 text-warning"></i>Cash Payment</h6>
                            <p class="text-muted small">
                                Payments can be made at the school bursar's office during working hours.<br>
                                <strong>Office Hours:</strong> Mon-Fri All the time.
                            </p>
                        </div>
                        
                        <div class="alert alert-secondary">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Always include your Index Number (<?php echo $student['index_number']; ?>) as reference when making payments.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payment History -->
        <div class="row mt-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-header" style="background-color: #3B9DB3; color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            Payment History (<?php echo count($payments); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No payment records found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Payment Method</th>
                                            <th>Reference</th>
                                            <th>Received By</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $index => $payment): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                            <td>
                                                <strong class="text-success">TZS <?php echo number_format($payment['amount'], 0); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $payment['payment_method']; ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($payment['reference_number'])): ?>
                                                    <small><?php echo htmlspecialchars($payment['reference_number']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($payment['admin_first'])): ?>
                                                    <?php echo htmlspecialchars($payment['admin_first'] . ' ' . $payment['admin_last']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($payment['notes'])): ?>
                                                    <small><?php echo htmlspecialchars($payment['notes']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="2" class="text-end">Total:</th>
                                            <th>TZS <?php echo number_format(array_sum(array_column($payments, 'amount')), 0); ?></th>
                                            <th colspan="4"></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- FAQ Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header" style="background-color: #6c757d; color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-question-circle me-2"></i>
                            Frequently Asked Questions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="faqAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                        What is the contribution for?
                                    </button>
                                </h2>
                                <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        The contribution of TZS 80,000 is for school equipment including farming tools, 
                                        cleaning equipment, and other school necessities. This helps ensure all students 
                                        have access to necessary tools for school activities.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                        When is the deadline for payment?
                                    </button>
                                </h2>
                                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        The deadline is typically at the end of the academic year (June 30th). 
                                        However, early payment is encouraged to avoid last-minute rush.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                        How can I get a receipt?
                                    </button>
                                </h2>
                                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Receipts are issued by the bursar's office upon payment. Please ensure you 
                                        collect your receipt and keep it for your records. For mobile payments, 
                                        the transaction message serves as proof of payment.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                        Can I pay in installments?
                                    </button>
                                </h2>
                                <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Yes, you can pay in installments. Partial payments are accepted and will be 
                                        recorded. Your status will show "Partially Paid" until the full amount is cleared.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.accordion-button:not(.collapsed) {
    background-color: #28a74520;
    color: #28a745;
}

.accordion-button:focus {
    box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
}

.progress-bar {
    transition: width 1s ease;
}

.display-4 {
    font-size: 3.5rem;
    font-weight: 600;
    color: #28a745;
}

@media (max-width: 768px) {
    .display-4 {
        font-size: 2.5rem;
    }
    
    .table {
        font-size: 0.9rem;
    }
}
</style>

<?php include '../controller/footer.php'; ?>