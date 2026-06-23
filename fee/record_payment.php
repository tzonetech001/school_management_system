<?php
// fee/record_payment.php
session_start();
ob_start();

// Simple test - if POST request, immediately return JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'POST received', 'post' => $_POST]);
    exit();
}

// Handle AJAX requests FIRST
if (isset($_POST['action'])) {
    error_log("=== AJAX HANDLER REACHED ===");
    error_log("Action: " . $_POST['action']);
    
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    require_once '../controller/db_connect.php';
    
    // Always return JSON for AJAX, even if not logged in
    if (!isset($_SESSION['admin_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit();
    }
    
    $admin_id = $_SESSION['admin_id'];
    
    $admin_sql = "SELECT school_id FROM admins WHERE id = ?";
    $stmt = mysqli_prepare($conn, $admin_sql);
    mysqli_stmt_bind_param($stmt, "i", $admin_id);
    mysqli_stmt_execute($stmt);
    $admin_result = mysqli_stmt_get_result($stmt);
    $admin = mysqli_fetch_assoc($admin_result);
    
    if (!$admin) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid admin']);
        exit();
    }
    $school_id = $admin['school_id'];
    
    $role_sql = "SELECT COUNT(*) as count FROM admin_role_assignments WHERE admin_id = ? AND role_id IN (1, 8)";
    $role_stmt = mysqli_prepare($conn, $role_sql);
    mysqli_stmt_bind_param($role_stmt, "i", $admin_id);
    mysqli_stmt_execute($role_stmt);
    $role_result = mysqli_stmt_get_result($role_stmt);
    $role_count = mysqli_fetch_assoc($role_result)['count'];
    
    if ($role_count == 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'record_payment') {
        $student_id = intval($_POST['student_id']);
        $amount = floatval($_POST['amount']);
        $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $reference = mysqli_real_escape_string($conn, $_POST['reference'] ?? '');
        $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
        
        if ($student_id <= 0 || $amount <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid student or amount']);
            exit();
        }
        
        $check_sql = "SELECT id FROM students WHERE id = ? AND school_id = ? AND status = 1";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $student_id, $school_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) == 0) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit();
        }
        mysqli_stmt_close($check_stmt);
        
        $insert_sql = "INSERT INTO student_payments (student_id, amount, payment_date, payment_method, reference_number, notes, recorded_by, school_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed')";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "idssssi", $student_id, $amount, $payment_date, $payment_method, $reference, $notes, $admin_id, $school_id);
        
        ob_clean();
        if (mysqli_stmt_execute($insert_stmt)) {
            echo json_encode(['success' => true, 'message' => 'Payment recorded successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        mysqli_stmt_close($insert_stmt);
        exit();
    }
    
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Normal page load
error_log("=== NORMAL PAGE LOAD ===");
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
$admin_result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($admin_result);

if (!$admin) {
    header("Location: ../index.php");
    exit();
}
$school_id = $admin['school_id'];

$role_sql = "SELECT COUNT(*) as count FROM admin_role_assignments WHERE admin_id = ? AND role_id IN (1, 8)";
$role_stmt = mysqli_prepare($conn, $role_sql);
mysqli_stmt_bind_param($role_stmt, "i", $admin_id);
mysqli_stmt_execute($role_stmt);
$role_result = mysqli_stmt_get_result($role_stmt);
$role_count = mysqli_fetch_assoc($role_result)['count'];

if ($role_count == 0) {
    header("Location: ../dashboard.php?error=unauthorized");
    exit();
}

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
        <h2 class="mb-4"><i class="fas fa-hand-holding-usd me-2" style="color: var(--primary-color);"></i>Record Payment</h2>

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
try {
    console.log('jQuery version:', $.fn.jquery);
    console.log('Document ready starting...');
} catch(e) {
    console.error('jQuery not loaded:', e);
}

$(document).ready(function() {
    try {
        console.log('Document ready fired');
    } catch(e) {
        console.error('Error in document ready:', e);
    }
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
        try {
            console.log('Submit event triggered');
            e.preventDefault();
            
            console.log('Form submitted');
        } catch(e) {
            console.error('Error in submit handler:', e);
        }
        
        const studentId = $('#selectedStudentId').val();
        console.log('Student ID:', studentId);
        
        if (!studentId || studentId == 0) {
            console.log('No student selected');
            Swal.fire('Error', 'Please select a student first.', 'error');
            return;
        }

        const amount = $('#amount').val();
        console.log('Amount:', amount);
        
        if (!amount || parseFloat(amount) <= 0) {
            console.log('Invalid amount');
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

        console.log('Sending data:', formData);
        
        try {
            $.ajax({
                url: 'record_payment.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    console.log('Response:', response);
                    if (response.success) {
                        Swal.fire('Success!', response.message, 'success');
                        $('#amount').val('');
                        $('#reference').val('');
                        $('#notes').val('');
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Full Response:', xhr.responseText);
                console.error('Response length:', xhr.responseText.length);
                console.error('First 1000 chars:', xhr.responseText.substring(0, 1000));
                Swal.fire('Error', 'Failed: ' + error + '. Check console for details.', 'error');
            }
            });
        } catch(e) {
            console.error('Error in AJAX call:', e);
            Swal.fire('Error', 'JavaScript error: ' + e.message, 'error');
        }
    });
});

// Global error handler
window.onerror = function(msg, url, lineNo, columnNo, error) {
    console.error('JavaScript Error:', msg, url, lineNo, columnNo, error);
    return false;
};

</script>

<?php include '../controller/footer.php'; ?>