<?php
// fee/record_payment.php
session_start();
require_once '../controller/db_connect.php';

// Check login
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

// Check if user is Bursar (role_id = 8) OR Head Master (role_id = 1)
$role_sql = "SELECT COUNT(*) as count 
             FROM admin_role_assignments ara 
             WHERE ara.admin_id = ? AND ara.role_id IN (1, 8)";
$role_stmt = mysqli_prepare($conn, $role_sql);
mysqli_stmt_bind_param($role_stmt, "i", $admin_id);
mysqli_stmt_execute($role_stmt);
$role_result = mysqli_stmt_get_result($role_stmt);
$role_count = mysqli_fetch_assoc($role_result)['count'];

if ($role_count == 0) {
    header("Location: ../dashboard.php?error=unauthorized");
    exit();
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'search_students') {
        $search = mysqli_real_escape_string($conn, $_POST['search'] ?? '');
        $query = "SELECT id, index_number, CONCAT(first_name, ' ', last_name) as full_name, class 
                  FROM students 
                  WHERE school_id = ? AND status = 1 AND is_leaver = 0 ";
        // If search is not empty, add conditions
        if (!empty($search)) {
            $like = "%$search%";
            $query .= " AND (index_number LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
        }
        $query .= " ORDER BY last_name, first_name LIMIT 50";
        
        $stmt = mysqli_prepare($conn, $query);
        if (!empty($search)) {
            mysqli_stmt_bind_param($stmt, "isss", $school_id, $like, $like, $like);
        } else {
            mysqli_stmt_bind_param($stmt, "i", $school_id);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $students = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $students[] = $row;
        }
        echo json_encode($students);
        exit();
    }
    
    if ($_POST['action'] === 'record_payment') {
        $student_id = intval($_POST['student_id']);
        $amount = floatval($_POST['amount']);
        $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $reference = mysqli_real_escape_string($conn, $_POST['reference'] ?? '');
        $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
        
        // Validate
        if ($student_id <= 0 || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid student or amount.']);
            exit();
        }
        
        // Check student belongs to this school
        $check_sql = "SELECT id FROM students WHERE id = ? AND school_id = ? AND status = 1";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $student_id, $school_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        if (mysqli_num_rows($check_result) == 0) {
            echo json_encode(['success' => false, 'message' => 'Student not found in this school.']);
            exit();
        }
        mysqli_stmt_close($check_stmt);
        
        // Insert payment
        $insert_sql = "INSERT INTO student_payments 
                       (student_id, amount, payment_date, payment_method, reference_number, notes, recorded_by, school_id, status)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed')";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "idssssi", $student_id, $amount, $payment_date, $payment_method, $reference, $notes, $admin_id, $school_id);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            echo json_encode(['success' => true, 'message' => 'Payment recorded successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
        mysqli_stmt_close($insert_stmt);
        exit();
    }
    
    if ($_POST['action'] === 'get_history') {
        $student_id = intval($_POST['student_id']);
        // Get payments
        $query = "SELECT p.*, CONCAT(a.first_name, ' ', a.last_name) as recorded_by_name 
                  FROM student_payments p
                  LEFT JOIN admins a ON p.recorded_by = a.id
                  WHERE p.student_id = ? AND p.school_id = ?
                  ORDER BY p.payment_date DESC, p.created_at DESC";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $student_id, $school_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $history = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $history[] = $row;
        }
        echo json_encode($history);
        exit();
    }
}

// Get current fee settings for display
$fee_settings = null;
$settings_sql = "SELECT * FROM fee_settings WHERE school_id = ? ORDER BY updated_at DESC LIMIT 1";
$settings_stmt = mysqli_prepare($conn, $settings_sql);
mysqli_stmt_bind_param($settings_stmt, "i", $school_id);
mysqli_stmt_execute($settings_stmt);
$settings_result = mysqli_stmt_get_result($settings_stmt);
$fee_settings = mysqli_fetch_assoc($settings_result);
mysqli_stmt_close($settings_stmt);

include '../controller/header.php';
include '../controller/sidebar.php';
?>

<style>
    .payment-form-container {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    .student-search-result {
        cursor: pointer;
        padding: 10px 15px;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.2s;
    }
    .student-search-result:hover {
        background: var(--primary-color);
        color: white;
    }
    .student-search-result:last-child {
        border-bottom: none;
    }
    .payment-history-table {
        font-size: 0.9rem;
    }
    .payment-history-table th {
        background: var(--primary-color);
        color: white;
        font-weight: 600;
    }
    #totalPaidDisplay {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-color);
    }
    .student-fee-summary {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        margin-top: 10px;
    }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-hand-holding-usd me-2" style="color: var(--primary-color);"></i>Record Payment</h2>
            <div>
                <?php if ($fee_settings): ?>
                    <span class="badge bg-success p-2 me-2">
                        Total Fee: TZS <?php echo number_format($fee_settings['total_fee'], 0); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <!-- Left: Payment Form -->
            <div class="col-lg-5">
                <div class="payment-form-container">
                    <h5 class="mb-3"><i class="fas fa-plus-circle me-2"></i>New Payment</h5>
                    <form id="paymentForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Student</label>
                            <input type="text" class="form-control" id="studentSearch" placeholder="Search by Index No or Name... (leave empty to see all)" autocomplete="off">
                            <div id="searchResults" class="mt-2" style="max-height: 250px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 8px; display: none;"></div>
                            <input type="hidden" id="selectedStudentId" value="">
                            <div id="selectedStudentDisplay" class="mt-2 text-muted" style="font-size: 0.9rem;"></div>
                            <div id="studentFeeSummary" class="student-fee-summary" style="display:none;"></div>
                        </div>

                        <div class="mb-3">
                            <label for="amount" class="form-label fw-bold">Amount (TZS)</label>
                            <input type="number" step="0.01" class="form-control" id="amount" placeholder="Enter amount" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="paymentDate" class="form-label">Payment Date</label>
                                <input type="date" class="form-control" id="paymentDate" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="paymentMethod" class="form-label">Payment Method</label>
                                <select class="form-select" id="paymentMethod">
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="mobile_money">Mobile Money</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="reference" class="form-label">Reference Number (Optional)</label>
                            <input type="text" class="form-control" id="reference" placeholder="Receipt or transaction ID">
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="notes" rows="2" placeholder="Additional notes..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-check me-2"></i>Record Payment
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right: Student Info & Payment History -->
            <div class="col-lg-7">
                <div class="payment-form-container">
                    <h5 class="mb-3"><i class="fas fa-history me-2"></i>Student Payment History</h5>
                    <div id="paymentHistoryContainer">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-search fa-3x mb-3 d-block opacity-50"></i>
                            <p>Search and select a student to view their payment history.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    let selectedStudentId = 0;
    let selectedStudentName = '';

    // Function to load students
    function loadStudents(query) {
        $.post('record_payment.php', { action: 'search_students', search: query }, function(data) {
            if (data.length > 0) {
                let html = '';
                data.forEach(s => {
                    html += `<div class="student-search-result" data-id="${s.id}" data-name="${s.full_name}">
                                <strong>${s.full_name}</strong> 
                                <span class="text-muted">(${s.index_number}) - ${s.class}</span>
                            </div>`;
                });
                $('#searchResults').html(html).show();
            } else {
                $('#searchResults').html('<div class="p-3 text-muted text-center">No students found.</div>').show();
            }
        }, 'json');
    }

    // Student search - trigger on input, and on focus if empty
    $('#studentSearch').on('input', function() {
        const query = $(this).val().trim();
        loadStudents(query);
    });

    $('#studentSearch').on('focus', function() {
        const query = $(this).val().trim();
        loadStudents(query);
    });

    // Click outside to hide results
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#studentSearch, #searchResults').length) {
            $('#searchResults').hide();
        }
    });

    // Select student from search
    $(document).on('click', '.student-search-result', function() {
        selectedStudentId = $(this).data('id');
        selectedStudentName = $(this).data('name');
        $('#selectedStudentId').val(selectedStudentId);
        $('#selectedStudentDisplay').html(`<i class="fas fa-check-circle text-success me-1"></i> Selected: <strong>${selectedStudentName}</strong>`);
        $('#searchResults').hide();
        $('#studentSearch').val(selectedStudentName);
        
        // Load history and fee summary
        loadPaymentHistory(selectedStudentId);
        loadFeeSummary(selectedStudentId);
    });

    // Load fee summary (total fee, paid, balance)
    function loadFeeSummary(studentId) {
        $.post('record_payment.php', { action: 'get_history', student_id: studentId }, function(data) {
            let totalPaid = 0;
            data.forEach(p => {
                totalPaid += parseFloat(p.amount);
            });
            // Get total fee from settings (we have it in PHP, but we need it client-side)
            // We'll fetch from a hidden field or from a separate endpoint.
            // For simplicity, we'll get it from the PHP variable passed to JS.
            // We'll inject the total fee as a data attribute.
            const totalFee = parseFloat('<?php echo $fee_settings ? $fee_settings['total_fee'] : 0; ?>') || 0;
            const balance = totalFee - totalPaid;
            const summaryHtml = `
                <div class="row text-center">
                    <div class="col-4">
                        <strong>Total Fee</strong><br>
                        <span class="text-primary">TZS ${totalFee.toLocaleString()}</span>
                    </div>
                    <div class="col-4">
                        <strong>Paid</strong><br>
                        <span class="text-success">TZS ${totalPaid.toLocaleString()}</span>
                    </div>
                    <div class="col-4">
                        <strong>Balance</strong><br>
                        <span class="text-${balance > 0 ? 'danger' : 'success'}">TZS ${balance.toLocaleString()}</span>
                    </div>
                </div>
            `;
            $('#studentFeeSummary').html(summaryHtml).show();
        }, 'json');
    }

    // Load payment history
    function loadPaymentHistory(studentId) {
        $('#paymentHistoryContainer').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Loading...</p></div>');
        
        $.post('record_payment.php', { action: 'get_history', student_id: studentId }, function(data) {
            if (data.length === 0) {
                $('#paymentHistoryContainer').html(`
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-receipt fa-3x mb-3 d-block opacity-50"></i>
                        <p>No payments recorded for this student yet.</p>
                    </div>
                `);
                return;
            }

            let totalPaid = 0;
            let html = `<div class="table-responsive">
                            <table class="table payment-history-table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount (TZS)</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                        <th>Recorded By</th>
                                    </tr>
                                </thead>
                                <tbody>`;
            data.forEach(p => {
                totalPaid += parseFloat(p.amount);
                html += `<tr>
                            <td>${p.payment_date}</td>
                            <td><strong>${Number(p.amount).toLocaleString()}</strong></td>
                            <td><span class="badge bg-secondary">${p.payment_method}</span></td>
                            <td>${p.reference_number || '-'}</td>
                            <td>${p.recorded_by_name || 'System'}</td>
                        </tr>`;
            });
            html += `</tbody></table></div>`;
            html += `<div class="mt-3 p-3 bg-light rounded">
                        <strong>Total Paid:</strong> 
                        <span id="totalPaidDisplay">TZS ${totalPaid.toLocaleString()}</span>
                     </div>`;
            $('#paymentHistoryContainer').html(html);
        }, 'json');
    }

    // Submit payment form
    $('#paymentForm').on('submit', function(e) {
        e.preventDefault();
        
        const studentId = $('#selectedStudentId').val();
        if (!studentId || studentId == 0) {
            Swal.fire('Error', 'Please select a student first.', 'error');
            return;
        }

        const amount = $('#amount').val();
        if (!amount || parseFloat(amount) <= 0) {
            Swal.fire('Error', 'Please enter a valid amount.', 'error');
            return;
        }

        const formData = {
            action: 'record_payment',
            student_id: studentId,
            amount: amount,
            payment_date: $('#paymentDate').val(),
            payment_method: $('#paymentMethod').val(),
            reference: $('#reference').val(),
            notes: $('#notes').val()
        };

        $.post('record_payment.php', formData, function(response) {
            if (response.success) {
                Swal.fire('Success!', response.message, 'success');
                // Reset form fields except student
                $('#amount').val('');
                $('#reference').val('');
                $('#notes').val('');
                // Reload history and summary
                loadPaymentHistory(studentId);
                loadFeeSummary(studentId);
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        }, 'json');
    });
});
</script>

<?php include '../controller/footer.php'; ?>