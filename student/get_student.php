<?php
require_once '../controller/db_connect.php';

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $sql = "SELECT * FROM students WHERE id = $id";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $student = mysqli_fetch_assoc($result);
        
        // Function to display field value or "Not provided"
        function displayValue($value) {
            return !empty($value) ? htmlspecialchars($value) : '<span class="text-muted">Not provided</span>';
        }
        ?>
        <div class="row">
            <div class="col-md-6">
                <h5 class="border-bottom pb-2 mb-3">Personal Information</h5>
                <table class="table table-sm">
                    <tr>
                        <th width="40%">Index Number:</th>
                        <td><?php echo displayValue($student['index_number']); ?></td>
                    </tr>
                    <tr>
                        <th>Full Name:</th>
                        <td><?php echo displayValue($student['first_name'] . ' ' . $student['second_name'] . ' ' . $student['last_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Sex:</th>
                        <td><?php echo displayValue($student['sex']); ?></td>
                    </tr>
                    <tr>
                        <th>Date of Birth:</th>
                        <td><?php echo displayValue($student['date_of_birth']); ?></td>
                    </tr>
                    <tr>
                        <th>Combination:</th>
                        <td><span class="badge bg-primary"><?php echo displayValue($student['combination']); ?></span></td>
                    </tr>
                    <tr>
                        <th>Citizenship:</th>
                        <td><?php echo displayValue($student['citizenship']); ?></td>
                    </tr>
                    <tr>
                        <th>Place of Birth:</th>
                        <td><?php echo displayValue($student['place_of_birth']); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="col-md-6">
                <h5 class="border-bottom pb-2 mb-3">Admission Details</h5>
                <table class="table table-sm">
                    <tr>
                        <th width="40%">Admission Number:</th>
                        <td><?php echo displayValue($student['admission_number']); ?></td>
                    </tr>
                    <tr>
                        <th>Date of Admission:</th>
                        <td><?php echo displayValue($student['date_of_admission']); ?></td>
                    </tr>
                    <tr>
                        <th>Class:</th>
                        <td><?php echo displayValue($student['class']); ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge <?php echo $student['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $student['status'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <h5 class="border-bottom pb-2 mb-3">Parent/Guardian Information</h5>
                <table class="table table-sm">
                    <tr>
                        <th width="40%">Name:</th>
                        <td><?php echo displayValue($student['parent_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Phone Number:</th>
                        <td><?php echo displayValue($student['parent_phone']); ?></td>
                    </tr>
                    <tr>
                        <th>Occupation:</th>
                        <td><?php echo displayValue($student['parent_occupation']); ?></td>
                    </tr>
                    <tr>
                        <th>Residence:</th>
                        <td><?php echo displayValue($student['parent_residence']); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="col-md-6">
                <h5 class="border-bottom pb-2 mb-3">Previous School Information</h5>
                <table class="table table-sm">
                    <tr>
                        <th width="40%">Former School:</th>
                        <td><?php echo displayValue($student['former_school']); ?></td>
                    </tr>
                    <tr>
                        <th>School Transferred To:</th>
                        <td><?php echo displayValue($student['school_transferred_to']); ?></td>
                    </tr>
                    <tr>
                        <th>Date Leaving School:</th>
                        <td><?php echo displayValue($student['date_leaving_school']); ?></td>
                    </tr>
                    <tr>
                        <th>School Transferred From:</th>
                        <td><?php echo displayValue($student['school_transferred_from']); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">Registered on: <?php echo date('F j, Y', strtotime($student['created_at'])); ?></small>
                    </div>
                    <div>
                        <a href="register.php?edit=<?php echo $student['id']; ?>" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit me-1"></i>Edit
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    } else {
        echo '<div class="alert alert-danger">Student not found.</div>';
    }
} else {
    echo '<div class="alert alert-warning">No student ID provided.</div>';
}
?>