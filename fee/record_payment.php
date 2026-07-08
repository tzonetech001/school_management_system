<?php
// fee/record_payment.php - Clean version without session_start at top
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Handle AJAX requests FIRST
if (isset($_POST['action'])) {
    // Start output buffering and clean any previous output
    while (ob_get_level()) { ob_end_clean(); }
    ob_start();
    
    // Set JSON header immediately
    header('Content-Type: application/json; charset=utf-8');
    
    // Helper function to return JSON cleanly
    $jsonResponse = function($data) {
        if (ob_get_level()) { ob_end_clean(); }
        echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
        exit();
    };
    
    // Debug logging
    $debugDir = __DIR__ . '/../logs';
    if (!is_dir($debugDir)) { @mkdir($debugDir, 0755, true); }
    $debugFile = $debugDir . '/ajax_debug.log';
    $debugEntry = "[".date('c')."] AJAX " . $_POST['action'] . "\n";
    @file_put_contents($debugFile, $debugEntry, FILE_APPEND);
    
    try {
        // Start session only if not started (avoid warnings)
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(['lifetime' => 86400, 'path' => '/', 'samesite' => 'Lax']);
            session_start();
        }
        
        require_once '../controller/db_connect.php';
        
        // Auth checks
        if (!isset($_SESSION['admin_id'])) {
            $jsonResponse(['success' => false, 'message' => 'Session expired. Please login again.']);
        }
        
        $admin_id = $_SESSION['admin_id'];
        $admin_sql = "SELECT school_id FROM admins WHERE id = ?";
        $stmt = mysqli_prepare($conn, $admin_sql);
        mysqli_stmt_bind_param($stmt, "i", $admin_id);
        mysqli_stmt_execute($stmt);
        $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        
        if (!$admin) {
            $jsonResponse(['success' => false, 'message' => 'Invalid admin']);
        }
        $school_id = $admin['school_id'];
        
        // Check role
        $role_sql = "SELECT COUNT(*) as count FROM admin_role_assignments WHERE admin_id = ? AND role_id IN (1, 8)";
        $role_stmt = mysqli_prepare($conn, $role_sql);
        mysqli_stmt_bind_param($role_stmt, "i", $admin_id);
        mysqli_stmt_execute($role_stmt);
        $role_count = mysqli_fetch_assoc(mysqli_stmt_get_result($role_stmt))['count'];
        mysqli_stmt_close($role_stmt);
        
        if ($role_count == 0) {
            $jsonResponse(['success' => false, 'message' => 'Unauthorized']);
        }
        
        // Get student summary
        if ($_POST['action'] === 'get_student_summary') {
            $student_id = intval($_POST['student_id']);
            if ($student_id <= 0) {
                $jsonResponse(['success' => false, 'message' => 'Invalid student']);
            }
            
            $fee_sql = "SELECT total_fee FROM fee_settings WHERE school_id = ? ORDER BY updated_at DESC LIMIT 1";
            $fee_stmt = mysqli_prepare($conn, $fee_sql);
            mysqli_stmt_bind_param($fee_stmt, "i", $school_id);
            mysqli_stmt_execute($fee_stmt);
            $fee_settings = mysqli_fetch_assoc(mysqli_stmt_get_result($fee_stmt));
            mysqli_stmt_close($fee_stmt);
            
            $total_fee = $fee_settings ? floatval($fee_settings['total_fee']) : 0;
            
            $paid_sql = "SELECT COALESCE(SUM(amount), 0) as total_paid FROM student_payments WHERE student_id = ? AND status = 'completed'";
            $paid_stmt = mysqli_prepare($conn, $paid_sql);
            mysqli_stmt_bind_param($paid_stmt, "i", $student_id);
            mysqli_stmt_execute($paid_stmt);
            $paid_data = mysqli_fetch_assoc(mysqli_stmt_get_result($paid_stmt));
            mysqli_stmt_close($paid_stmt);
            
            $total_paid = floatval($paid_data['total_paid']);
            $balance = $total_fee - $total_paid;
            $status = $balance <= 0 ? 'Paid in Full' : ($total_paid > 0 ? 'Partial' : 'Not Paid');
            
            $jsonResponse([
                'success' => true,
                'data' => [
                    'total_fee' => $total_fee,
                    'total_paid' => $total_paid,
                    'balance' => $balance,
                    'status' => $status
                ]
            ]);
        }
        
        // Record payment
        if ($_POST['action'] === 'record_payment') {
            $student_id = intval($_POST['student_id']);
            $amount = floatval($_POST['amount']);
            $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
            $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
            $reference = mysqli_real_escape_string($conn, $_POST['reference'] ?? '');
            $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
            
            if ($student_id <= 0 || $amount <= 0) {
                $jsonResponse(['success' => false, 'message' => 'Invalid student or amount']);
            }
            
            $check_sql = "SELECT id FROM students WHERE id = ? AND school_id = ? AND status = 1";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "ii", $student_id, $school_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) == 0) {
                mysqli_stmt_close($check_stmt);
                $jsonResponse(['success' => false, 'message' => 'Student not found']);
            }
            mysqli_stmt_close($check_stmt);
            
            $insert_sql = "INSERT INTO student_payments (student_id, amount, payment_date, payment_method, reference_number, notes, recorded_by, school_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed')";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "idssssii", $student_id, $amount, $payment_date, $payment_method, $reference, $notes, $admin_id, $school_id);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                mysqli_stmt_close($insert_stmt);
                $jsonResponse(['success' => true, 'message' => 'Payment recorded successfully!']);
            } else {
                $dbErr = mysqli_error($conn);
                mysqli_stmt_close($insert_stmt);
                $logDir = __DIR__ . '/../logs';
                if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
                @file_put_contents($logDir . '/ajax_errors.log', "[".date('c')."] DB error: " . $dbErr . "\n", FILE_APPEND);
                $jsonResponse(['success' => false, 'message' => 'Database error']);
            }
        }
        
        $jsonResponse(['success' => false, 'message' => 'Invalid action']);
        
    } catch (Throwable $e) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        $logFile = $logDir . '/ajax_errors.log';
        $msg = "[".date('c')."] Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n\n";
        @file_put_contents($logFile, $msg, FILE_APPEND);
        $jsonResponse(['success' => false, 'message' => 'Server error', 'detail' => $e->getMessage()]);
    }
}

// Normal page load - NO SESSION START HERE, db_connect handles it
$_SERVER['REQUEST_METHOD'] = 'GET'; // Prevent double session start

require_once '../controller/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_sql = "SELECT school_id FROM admins WHERE id = ?";
$stmt = mysqli_prepare($conn, $admin_sql);
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);
$admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$admin) {
    header("Location: ../index.php");
    exit();
}
$school_id = $admin['school_id'];

$role_sql = "SELECT COUNT(*) as count FROM admin_role_assignments WHERE admin_id = ? AND role_id IN (1, 8)";
$role_stmt = mysqli_prepare($conn, $role_sql);
mysqli_stmt_bind_param($role_stmt, "i", $admin_id);
mysqli_stmt_execute($role_stmt);
$role_count = mysqli_fetch_assoc(mysqli_stmt_get_result($role_stmt))['count'];
mysqli_stmt_close($role_stmt);

if ($role_count == 0) {
    header("Location: ../dashboard.php?error=unauthorized");
    exit();
}

$fee_settings = null;
$settings_sql = "SELECT * FROM fee_settings WHERE school_id = ? ORDER BY updated_at DESC LIMIT 1";
$settings_stmt = mysqli_prepare($conn, $settings_sql);
mysqli_stmt_bind_param($settings_stmt, "i", $school_id);
mysqli_stmt_execute($settings_stmt);
$fee_settings = mysqli_fetch_assoc(mysqli_stmt_get_result($settings_stmt));
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
        max-width: 800px;
        margin: 0 auto;
    }
    .student-search-results-table {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        margin-top: 10px;
        display: none;
    }
    .student-row-select {
        cursor: pointer;
        transition: all 0.2s;
    }
    .student-row-select:hover {
        background: #f8f9fa;
    }
    .student-row-select.selected {
        background: #e8f4f8;
        border-left: 3px solid #3B9DB3;
    }
    .selected-student-summary {
        background: linear-gradient(135deg, #f0fff4, #ffffff);
        border: 2px solid #28a745;
        border-radius: 8px;
        padding: 15px;
        margin-top: 10px;
        display: none;
    }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-hand-holding-usd me-2" style="color: var(--primary-color);"></i>Record Payment</h2>
            <a href="payments.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-file-invoice me-1"></i>View Payment Reports
            </a>
        </div>

        <div class="payment-form-container">
            <h5 class="mb-3"><i class="fas fa-plus-circle me-2"></i>New Payment</h5>
            <form id="paymentForm">
                <div class="mb-3">
                    <label class="form-label fw-bold">Student</label>
                    <div class="input-group mb-2">
                        <span class="input-group-text bg-primary text-white">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" id="studentSearch" 
                               placeholder="Type to search by name, index number, or admission number..." 
                               autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="student-search-results-table" id="studentSearchResults" 
                         style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; display: none;">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">Select</th>
                                    <th width="25%">Name</th>
                                    <th width="15%">Index Number</th>
                                    <th width="15%">Class</th>
                                    <th width="15%">Combination</th>
                                </tr>
                            </thead>
                            <tbody id="studentResultsBody"></tbody>
                        </table>
                    </div>
                    
                    <div class="selected-student-summary" id="selectedStudentSummary" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>Selected Student</h6>
                                <div class="d-flex flex-wrap gap-3">
                                    <div><strong>Name:</strong> <span id="selectedStudentName">-</span></div>
                                    <div><strong>Index:</strong> <span id="selectedStudentIndex">-</span></div>
                                    <div><strong>Class:</strong> <span id="selectedStudentClass">-</span></div>
                                    <div><strong>Combination:</strong> <span id="selectedStudentCombination">-</span></div>
                                </div>
                                <div class="mt-3 row g-2">
                                    <div class="col-md-6"><strong>Total Fee:</strong> <span id="selectedStudentTotalFee">TZS -</span></div>
                                    <div class="col-md-6"><strong>Paid:</strong> <span id="selectedStudentTotalPaid">TZS -</span></div>
                                    <div class="col-md-6"><strong>Balance:</strong> <span id="selectedStudentBalance">TZS -</span></div>
                                    <div class="col-md-6"><strong>Status:</strong> <span id="selectedStudentStatus">-</span></div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearStudentSelection()">
                                <i class="fas fa-times me-1"></i>Change
                            </button>
                        </div>
                    </div>
                    <input type="hidden" id="selectedStudentId" value="">
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
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    let selectedStudentId = 0;
    let searchTimeout;

    function loadStudents(query) {
        $.ajax({
            url: 'search_students.php',
            type: 'GET',
            data: { q: query },
            dataType: 'json',
            success: function(data) {
                const tbody = $('#studentResultsBody');
                tbody.empty();
                
                if (!data || data.length === 0) {
                    tbody.html('<tr><td colspan="5" class="text-center py-3 text-muted">No students found</td></tr>');
                } else {
                    data.forEach(s => {
                        const tr = $(`
                            <tr class="student-row-select">
                                <td class="text-center">
                                    <input type="radio" name="student_radio" value="${s.id}" 
                                           onchange="selectStudent(${s.id}, '${(s.name || '').replace(/'/g, "\\'")}', 
                                           '${s.index_number || ''}', '${s.class || ''}', '${s.combination || ''}')">
                                </td>
                                <td class="fw-bold">${s.name || 'N/A'}</td>
                                <td>${s.index_number || '-'}</td>
                                <td>${s.class || '-'}</td>
                                <td>${s.combination || '-'}</td>
                            </tr>
                        `);
                        tbody.append(tr);
                    });
                }
                $('#studentSearchResults').show();
            },
            error: function(xhr, status, error) {
                console.error('Search error:', error);
                const tbody = $('#studentResultsBody');
                tbody.html('<tr><td colspan="5" class="text-center py-3 text-danger">Error: ' + error + '</td></tr>');
                $('#studentSearchResults').show();
            }
        });
    }

    $('#studentSearch').on('input', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val().trim();
        
        if (query.length < 1) {
            $('#studentSearchResults').hide();
            return;
        }
        
        searchTimeout = setTimeout(() => loadStudents(query), 300);
    });

    $('#studentSearch').on('focus', function() {
        const query = $(this).val().trim();
        if (query.length >= 1) loadStudents(query);
    });

    $('#clearSearch').on('click', function() {
        $('#studentSearch').val('');
        $('#studentSearchResults').hide();
        clearStudentSelection();
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#studentSearchResults') && 
            !$(e.target).closest('#studentSearch') && 
            !$(e.target).closest('#clearSearch')) {
            $('#studentSearchResults').hide();
        }
    });

    function updateStudentSummaryDisplay(summary) {
        $('#selectedStudentTotalFee').text('TZS ' + Number(summary.total_fee).toLocaleString('en-US', {maximumFractionDigits: 2}));
        $('#selectedStudentTotalPaid').text('TZS ' + Number(summary.total_paid).toLocaleString('en-US', {maximumFractionDigits: 2}));
        $('#selectedStudentBalance').text('TZS ' + Number(summary.balance).toLocaleString('en-US', {maximumFractionDigits: 2}));
        $('#selectedStudentStatus').text(summary.status);
    }

    function loadStudentSummary(studentId) {
        if (!studentId || studentId == 0) {
            console.log('No student ID provided');
            return;
        }

        console.log('Loading student summary for ID:', studentId);
        
        $.ajax({
            url: 'process_payment.php',
            type: 'POST',
            data: { action: 'get_student_summary', student_id: studentId },
            dataType: 'json',
            success: function(response) {
                console.log('Student summary response:', response);
                if (response && response.success && response.data) {
                    console.log('Updating display with:', response.data);
                    updateStudentSummaryDisplay(response.data);
                } else {
                    console.warn('Unable to load student summary', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('Could not load student summary:', error, xhr.status, xhr.responseText);
            }
        });
    }

    window.selectStudent = function(studentId, studentName, indexNumber, studentClass, combination) {
        selectedStudentId = studentId;
        $('#selectedStudentId').val(studentId);
        $('#selectedStudentName').text(studentName);
        $('#selectedStudentIndex').text(indexNumber);
        $('#selectedStudentClass').text(studentClass);
        $('#selectedStudentCombination').text(combination || '-');
        $('#selectedStudentSummary').show();
        $('#studentSearchResults').hide();
        $('#studentSearch').val(studentName);
        
        $('.student-row-select').removeClass('selected');
        if (event && event.target) {
            $(event.target).closest('tr').addClass('selected');
        }

        loadStudentSummary(studentId);
    };

    window.clearStudentSelection = function() {
        selectedStudentId = 0;
        $('#selectedStudentId').val('');
        $('#selectedStudentSummary').hide();
        $('#studentSearch').val('');
        $('#studentSearchResults').hide();
        $('input[name="student_radio"]').prop('checked', false);
        $('.student-row-select').removeClass('selected');
    };

    $('#paymentForm').on('submit', function(e) {
        e.preventDefault();
        
        const studentId = parseInt($('#selectedStudentId').val()) || 0;
        const amount = parseFloat($('#amount').val()) || 0;
        
        console.log('Submitting payment - Student ID:', studentId, 'Amount:', amount);
        
        if (studentId <= 0) {
            Swal.fire('Error', 'Please select a student first.', 'error');
            return;
        }

        if (amount <= 0) {
            Swal.fire('Error', 'Please enter a valid amount.', 'error');
            return;
        }
        
        if (!amount || parseFloat(amount) <= 0) {
            Swal.fire('Error', 'Please enter a valid amount.', 'error');
            return;
        }

        const postData = {
            action: 'record_payment',
            student_id: studentId,
            amount: amount,
            payment_date: $('#paymentDate').val(),
            payment_method: $('#paymentMethod').val(),
            reference: $('#reference').val(),
            notes: $('#notes').val()
        };

        console.log('Posting data:', postData);
        
        const $submitBtn = $(this).find('button[type="submit"]');
        const originalBtnHtml = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Recording...');

        $.ajax({
            url: 'process_payment.php',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(response) {
                // dataType:'json' means jQuery has already parsed the response.
                console.log('Parsed response:', response);
                if (response && response.success) {
                    Swal.fire('Success!', response.message, 'success');
                    $('#amount').val('');
                    $('#reference').val('');
                    $('#notes').val('');
                    loadStudentSummary(studentId);

                    // Notify any open Payment Report tab to refresh its figures.
                    try {
                        localStorage.setItem('payments_update', JSON.stringify({
                            student_id: studentId,
                            ts: new Date().getTime()
                        }));
                    } catch (e) { /* localStorage unavailable - ignore */ }
                } else {
                    Swal.fire('Error', (response && response.message) || 'Unknown error', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                Swal.fire('Error', 'Request failed: ' + error, 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalBtnHtml);
            }
        });
    });
});

// Global error handler
window.onerror = function(msg, url, lineNo, columnNo, error) {
    console.error('JavaScript Error:', msg, url, lineNo, columnNo, error);
    return false;
};
</script>

<?php include '../controller/footer.php'; ?>