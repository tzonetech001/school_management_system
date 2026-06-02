<?php
// super/get_school.php - Get School Data for AJAX
session_start();

// Check if Super Admin is logged in
if (!isset($_SESSION['super_admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../controller/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $school_id = (int)$_POST['id'];
    
    if (isset($_POST['view'])) {
        // Return HTML for view modal
        $sql = "SELECT s.*,
                (SELECT COUNT(*) FROM students st WHERE st.school_id = s.id AND st.is_leaver = 0 AND st.status = 1) as total_students,
                (SELECT COUNT(*) FROM admins a WHERE a.school_id = s.id AND a.status = 1) as total_admins,
                (SELECT COUNT(*) FROM students st WHERE st.school_id = s.id AND st.sex = 'Male' AND st.is_leaver = 0) as male_students,
                (SELECT COUNT(*) FROM students st WHERE st.school_id = s.id AND st.sex = 'Female' AND st.is_leaver = 0) as female_students,
                (SELECT COUNT(*) FROM dormitories d WHERE d.school_id = s.id) as total_dorms,
                (SELECT COALESCE(SUM(current_occupancy), 0) FROM dormitories d WHERE d.school_id = s.id) as dorm_occupancy,
                (SELECT COALESCE(SUM(total_capacity), 0) FROM dormitories d WHERE d.school_id = s.id) as dorm_capacity
                FROM schools s WHERE s.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $school = $result->fetch_assoc();
        
        if ($school) {
            $dorm_percent = $school['dorm_capacity'] > 0 ? round(($school['dorm_occupancy'] / $school['dorm_capacity']) * 100, 1) : 0;
            ?>
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr><td width="40%"><strong>School Code:</strong></td><td><?php echo htmlspecialchars($school['school_code']); ?></td></tr>
                        <tr><td><strong>School Name:</strong></td><td><?php echo htmlspecialchars($school['school_name']); ?></td></tr>
                        <tr><td><strong>Motto:</strong></td><td><?php echo htmlspecialchars($school['school_motto'] ?? '-'); ?></td></tr>
                        <tr><td><strong>Registration:</strong></td><td><?php echo htmlspecialchars($school['registration_number'] ?? '-'); ?></td></tr>
                        <tr><td><strong>Status:</strong></td><td><span class="status-badge status-<?php echo $school['status']; ?>"><?php echo $school['status']; ?></span></td></tr>
                        <tr><td><strong>Plan:</strong></td><td><?php echo $school['subscription_plan']; ?></td></tr>
                        <tr><td><strong>Expiry:</strong></td><td><?php echo $school['subscription_expiry'] ? date('F d, Y', strtotime($school['subscription_expiry'])) : 'Never'; ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr><td width="40%"><strong>Phone:</strong></td><td><?php echo htmlspecialchars($school['phone'] ?? '-'); ?></td></tr>
                        <tr><td><strong>Email:</strong></td><td><?php echo htmlspecialchars($school['email'] ?? '-'); ?></td></tr>
                        <tr><td><strong>Address:</strong></td><td><?php echo nl2br(htmlspecialchars($school['address'] ?? '-')); ?></td></tr>
                        <tr><td><strong>Owner:</strong></td><td><?php echo htmlspecialchars($school['owner_name'] ?? '-'); ?></td></tr>
                        <tr><td><strong>Owner Phone:</strong></td><td><?php echo htmlspecialchars($school['owner_phone'] ?? '-'); ?></td></tr>
                        <tr><td><strong>Max Students:</strong></td><td><?php echo number_format($school['max_students']); ?></td></tr>
                        <tr><td><strong>Max Admins:</strong></td><td><?php echo number_format($school['max_admins']); ?></td></tr>
                    </table>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-4">
                    <div class="text-center">
                        <h3 class="text-primary"><?php echo number_format($school['total_students']); ?></h3>
                        <p class="text-muted">Total Students</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <h3 class="text-success"><?php echo number_format($school['total_admins']); ?></h3>
                        <p class="text-muted">Staff/Admins</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <h3 class="text-info"><?php echo $dorm_percent; ?>%</h3>
                        <p class="text-muted">Dorm Occupancy</p>
                    </div>
                </div>
            </div>
            <?php
        } else {
            echo '<div class="text-center py-4 text-danger">School not found</div>';
        }
        $stmt->close();
    } else {
        // Return JSON for edit modal
        $sql = "SELECT * FROM schools WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $school = $result->fetch_assoc();
        
        if ($school) {
            echo json_encode($school);
        } else {
            echo json_encode(['error' => 'School not found']);
        }
        $stmt->close();
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>