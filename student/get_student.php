<?php
// get_student.php - AJAX endpoint for student details WITH SCHOOL ID FILTERING
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo '<div class="alert alert-danger">Unauthorized access.</div>';
    exit();
}

$admin_id = $_SESSION['admin_id'];

// ========== GET CURRENT SCHOOL ID AND SCHOOL CODE ==========
$school_query = "SELECT school_id, school_code FROM admins a JOIN schools s ON a.school_id = s.id WHERE a.id = ?";
$stmt = $conn->prepare($school_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$school_result = $stmt->get_result();
$current_admin_data = $school_result->fetch_assoc();
$current_school_id = $current_admin_data['school_id'] ?? 1;
$current_school_code = $current_admin_data['school_code'] ?? 'MVZ001';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-warning">No student ID provided.</div>';
    exit();
}

$id = intval($_GET['id']);
$is_leaver = isset($_GET['leaver']) ? true : false;

// Build query with school_id filtering
if ($is_leaver) {
    $sql = "SELECT s.*, 
                   sl.reason, sl.leaver_type, sl.left_at, sl.returned,
                   sl.returned_at
            FROM students s
            LEFT JOIN student_leavers sl ON s.id = sl.student_id AND sl.school_id = s.school_id
            WHERE s.id = ? AND s.school_id = ?";
} else {
    $sql = "SELECT * FROM students WHERE id = ? AND school_id = ?";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id, $current_school_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $student = $result->fetch_assoc();
    
    // Function to display field value or "Not provided"
    function displayValue($value) {
        return !empty($value) ? htmlspecialchars($value) : '<span class="text-muted">Not provided</span>';
    }
    
    // Get the school code for display (from student's index number or from current school)
    $student_school_code = $current_school_code;
    if (!empty($student['index_number'])) {
        $index_parts = explode('-', $student['index_number']);
        if (count($index_parts) > 0) {
            $student_school_code = $index_parts[0];
        }
    }
    ?>
    <style>
        .student-detail-card {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .detail-section {
            margin-bottom: 20px;
        }
        .detail-section h5 {
            color: #3B9DB3;
            border-left: 4px solid #3B9DB3;
            padding-left: 12px;
            margin-bottom: 15px;
        }
        .info-badge {
            background: linear-gradient(135deg, #3B9DB3, #2d7c8f);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        .table-sm th {
            font-weight: 600;
            color: #555;
            background-color: #f8f9fa;
            width: 40%;
        }
        .table-sm td {
            color: #333;
        }
        .school-code-badge {
            background: #6c757d;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
            margin-left: 10px;
        }
    </style>
    
    <div class="student-detail-card">
        <!-- Header with School Code -->
        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
            <div>
                <h4 class="mb-0">
                    <i class="fas fa-user-graduate me-2" style="color: #3B9DB3;"></i>
                    Student Details
                    <span class="school-code-badge">
                        <i class="fas fa-code me-1"></i><?php echo htmlspecialchars($student_school_code); ?>
                    </span>
                </h4>
            </div>
            <div>
                <span class="info-badge">
                    <i class="fas fa-calendar-alt me-1"></i>
                    <?php echo date('M d, Y', strtotime($student['created_at'])); ?>
                </span>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="detail-section">
                    <h5><i class="fas fa-user-circle me-2"></i>Personal Information</h5>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th width="40%"><i class="fas fa-hashtag text-muted me-1"></i>Index Number:</th>
                            <td><strong><?php echo displayValue($student['index_number']); ?></strong></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-user me-1"></i>Full Name:</th>
                            <td><?php echo displayValue($student['first_name'] . ' ' . ($student['second_name'] ?? '') . ' ' . $student['last_name']); ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-<?php echo ($student['sex'] == 'Male') ? 'mars' : 'venus'; ?> me-1"></i>Sex:</th>
                            <td>
                                <?php if ($student['sex'] == 'Male'): ?>
                                    <span class="badge bg-primary"><i class="fas fa-mars me-1"></i>Male</span>
                                <?php elseif ($student['sex'] == 'Female'): ?>
                                    <span class="badge" style="background-color: #e83e8c;"><i class="fas fa-venus me-1"></i>Female</span>
                                <?php else: ?>
                                    <?php echo displayValue($student['sex']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-birthday-cake me-1"></i>Date of Birth:</th>
                            <td><?php echo displayValue($student['date_of_birth']); ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-layer-group me-1"></i>Combination:</th>
                            <td><span class="badge bg-primary"><?php echo displayValue($student['combination']); ?></span></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-passport me-1"></i>Citizenship:</th>
                            <td><?php echo displayValue($student['citizenship']); ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-map-marker-alt me-1"></i>Place of Birth:</th>
                            <td><?php echo displayValue($student['place_of_birth']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="detail-section">
                    <h5><i class="fas fa-school me-2"></i>Admission Details</h5>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th width="40%"><i class="fas fa-id-card me-1"></i>Admission Number:</th>
                            <td><strong><?php echo displayValue($student['admission_number']); ?></strong></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-calendar-plus me-1"></i>Date of Admission:</th>
                            <td><?php echo displayValue($student['date_of_admission']); ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-graduation-cap me-1"></i>Class:</th>
                            <td>
                                <span class="badge <?php echo ($student['class'] == 'Form Five') ? 'bg-success' : 'bg-info'; ?>">
                                    <?php echo displayValue($student['class']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-toggle-on me-1"></i>Status:</th>
                            <td>
                                <?php if (isset($student['is_leaver']) && $student['is_leaver'] == 1): ?>
                                    <span class="badge bg-warning">
                                        <i class="fas fa-sign-out-alt me-1"></i>Leaver/Graduate
                                    </span>
                                    <?php if (!empty($student['reason'])): ?>
                                        <div class="small text-muted mt-1">Reason: <?php echo htmlspecialchars($student['reason']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($student['left_at'])): ?>
                                        <div class="small text-muted">Left on: <?php echo date('M d, Y', strtotime($student['left_at'])); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($student['returned']) && $student['returned'] == 1): ?>
                                        <div class="small text-success">Returned on: <?php echo date('M d, Y', strtotime($student['returned_at'])); ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge <?php echo $student['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <i class="fas <?php echo $student['status'] ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                                        <?php echo $student['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($student['graduation_status']) && $student['graduation_status'] != 'Active'): ?>
                        <tr>
                            <th><i class="fas fa-graduation-cap me-1"></i>Graduation Status:</th>
                            <td>
                                <span class="badge bg-secondary">
                                    <?php echo displayValue($student['graduation_status']); ?>
                                </span>
                                <?php if (!empty($student['graduation_year'])): ?>
                                    <span class="badge bg-dark ms-1">Class of <?php echo $student['graduation_year']; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="row mt-2">
            <div class="col-md-6">
                <div class="detail-section">
                    <h5><i class="fas fa-users me-2"></i>Parent/Guardian Information</h5>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th width="40%"><i class="fas fa-user-tie me-1"></i>Name:</th>
                            <td><?php echo displayValue($student['parent_name']); ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-phone me-1"></i>Phone Number:</th>
                            <td>
                                <?php if (!empty($student['parent_phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($student['parent_phone']); ?>" class="text-decoration-none">
                                        <i class="fas fa-phone-alt me-1"></i><?php echo htmlspecialchars($student['parent_phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-briefcase me-1"></i>Occupation:</th>
                            <td><?php echo displayValue($student['parent_occupation']); ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-home me-1"></i>Residence:</th>
                            <td><?php echo displayValue($student['parent_residence']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="detail-section">
                    <h5><i class="fas fa-book me-2"></i>Previous School Information</h5>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th width="40%"><i class="fas fa-university me-1"></i>Former School:</th>
                            <td><?php echo displayValue($student['former_school']); ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-exchange-alt me-1"></i>Transferred To:</th>
                            <td><?php echo displayValue($student['school_transferred_to']); ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-calendar-times me-1"></i>Date Leaving:</th>
                            <td><?php echo displayValue($student['date_leaving_school']); ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-exchange-alt me-1"></i>Transferred From:</th>
                            <td><?php echo displayValue($student['school_transferred_from']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <?php if ($is_leaver && !empty($student['reason'])): ?>
        <div class="row mt-2">
            <div class="col-md-12">
                <div class="detail-section">
                    <h5><i class="fas fa-info-circle me-2"></i>Leaver Information</h5>
                    <div class="alert alert-info">
                        <strong>Reason for leaving:</strong> <?php echo htmlspecialchars($student['reason']); ?>
                        <hr class="my-2">
                        <strong>Leaver Type:</strong> <?php echo displayValue($student['leaver_type']); ?>
                        <br>
                        <strong>Left Date:</strong> <?php echo date('F j, Y', strtotime($student['left_at'])); ?>
                        <?php if (!empty($student['returned']) && $student['returned'] == 1): ?>
                            <br>
                            <strong>Returned:</strong> Yes (<?php echo date('F j, Y', strtotime($student['returned_at'])); ?>)
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                    <div>
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            Registered on: <?php echo date('F j, Y \a\t g:i A', strtotime($student['created_at'])); ?>
                        </small>
                        <?php if (!empty($student['updated_at']) && $student['updated_at'] != $student['created_at']): ?>
                            <br>
                            <small class="text-muted">
                                <i class="fas fa-edit me-1"></i>
                                Last updated: <?php echo date('F j, Y \a\t g:i A', strtotime($student['updated_at'])); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="window.print();">
                            <i class="fas fa-print me-1"></i>Print
                        </button>
                        <a href="register.php?edit=<?php echo $student['id']; ?>" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit me-1"></i>Edit Student
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
} else {
    echo '<div class="alert alert-danger">Student not found or you do not have permission to view this student.</div>';
}
?>