<?php
// get_room_details.php
session_start();
require_once '../controller/db_connect.php';

header('Content-Type: text/html');

if (!isset($_GET['room_id'])) {
    echo '<div class="alert alert-danger">Room ID is required.</div>';
    exit();
}

$room_id = mysqli_real_escape_string($conn, $_GET['room_id']);

// Get room details with dormitory information
$room_sql = "SELECT dr.*, d.dorm_name, d.dorm_type, d.description as dorm_description
            FROM dormitory_rooms dr
            JOIN dormitories d ON dr.dormitory_id = d.id
            WHERE dr.id = $room_id";

$room_result = mysqli_query($conn, $room_sql);

if (!$room_result || mysqli_num_rows($room_result) == 0) {
    echo '<div class="alert alert-danger">Room not found.</div>';
    exit();
}

$room = mysqli_fetch_assoc($room_result);
$available_beds = $room['capacity'] - $room['current_occupancy'];
$occupancy_percent = $room['capacity'] > 0 ? ($room['current_occupancy'] / $room['capacity']) * 100 : 0;

// Get students assigned to this room
$students_sql = "SELECT s.first_name, s.last_name, s.index_number, s.class, s.combination, sd.bed_number, sd.assigned_at
                FROM student_dormitory sd
                JOIN students s ON sd.student_id = s.id
                WHERE sd.room_id = $room_id AND sd.status = 'Active'
                ORDER BY s.first_name, s.last_name";

$students_result = mysqli_query($conn, $students_sql);
$students = [];
if ($students_result && mysqli_num_rows($students_result) > 0) {
    while ($row = mysqli_fetch_assoc($students_result)) {
        $students[] = $row;
    }
}

// Get room status history
$history_sql = "SELECT * FROM room_status_logs 
               WHERE room_id = $room_id 
               ORDER BY changed_at DESC 
               LIMIT 5";
$history_result = mysqli_query($conn, $history_sql);
$history = [];
if ($history_result && mysqli_num_rows($history_result) > 0) {
    while ($row = mysqli_fetch_assoc($history_result)) {
        $history[] = $row;
    }
}
?>

<div class="row">
    <div class="col-md-6">
        <h5 class="mb-3">Room Information</h5>
        <table class="table table-sm">
            <tr>
                <th width="40%">Room Number:</th>
                <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
            </tr>
            <tr>
                <th>Room Label:</th>
                <td><?php echo htmlspecialchars($room['room_label']); ?></td>
            </tr>
            <tr>
                <th>Dormitory:</th>
                <td>
                    <span class="badge" style="background-color: <?php echo $room['dorm_type'] == 'Male' ? '#007bff' : '#e83e8c'; ?>; color: white;">
                        <?php echo htmlspecialchars($room['dorm_name']); ?>
                    </span>
                    <i class="fas fa-<?php echo $room['dorm_type'] == 'Male' ? 'male' : 'female'; ?>" 
                       style="color: <?php echo $room['dorm_type'] == 'Male' ? '#007bff' : '#e83e8c'; ?>; margin-left: 5px;"></i>
                </td>
            </tr>
            <tr>
                <th>Capacity:</th>
                <td>
                    <span class="badge bg-secondary"><?php echo $room['capacity']; ?> beds</span>
                </td>
            </tr>
            <tr>
                <th>Current Occupancy:</th>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="progress flex-grow-1 me-2" style="height: 15px;">
                            <div class="progress-bar 
                                <?php echo $occupancy_percent >= 100 ? 'bg-danger' : ($occupancy_percent >= 80 ? 'bg-warning' : 'bg-success'); ?>" 
                                role="progressbar" 
                                style="width: <?php echo min($occupancy_percent, 100); ?>%;">
                            </div>
                        </div>
                        <span class="badge bg-dark"><?php echo $room['current_occupancy']; ?>/<?php echo $room['capacity']; ?></span>
                    </div>
                </td>
            </tr>
            <tr>
                <th>Available Beds:</th>
                <td>
                    <?php if ($available_beds > 0): ?>
                        <span class="badge bg-success"><?php echo $available_beds; ?> beds available</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Room is full</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Status:</th>
                <td>
                    <?php 
                    $status_class = '';
                    switch($room['status']) {
                        case 'Available': $status_class = 'bg-success'; break;
                        case 'Full': $status_class = 'bg-danger'; break;
                        case 'Maintenance': $status_class = 'bg-warning'; break;
                        default: $status_class = 'bg-secondary';
                    }
                    ?>
                    <span class="badge <?php echo $status_class; ?>">
                        <?php echo $room['status']; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Created:</th>
                <td><?php echo date('F j, Y', strtotime($room['created_at'])); ?></td>
            </tr>
            <tr>
                <th>Last Updated:</th>
                <td><?php echo date('F j, Y', strtotime($room['updated_at'])); ?></td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h5 class="mb-3">Assigned Students (<?php echo count($students); ?>)</h5>
        <?php if (empty($students)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No students assigned to this room.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Index No.</th>
                            <th>Class</th>
                            <th>Bed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $index => $student): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($student['index_number']); ?></small></td>
                            <td>
                                <span class="badge bg-secondary"><?php echo $student['class']; ?></span>
                                <br>
                                <small class="text-muted"><?php echo $student['combination']; ?></small>
                            </td>
                            <td>
                                <?php if ($student['bed_number']): ?>
                                    <span class="badge bg-warning text-dark"><?php echo $student['bed_number']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Not set</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($history)): ?>
<div class="row mt-4">
    <div class="col-12">
        <h5 class="mb-3">Recent Status History</h5>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Old Status</th>
                        <th>New Status</th>
                        <th>Changed By</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $log): ?>
                    <tr>
                        <td><?php echo date('M j, Y H:i', strtotime($log['changed_at'])); ?></td>
                        <td><span class="badge bg-secondary"><?php echo $log['old_status']; ?></span></td>
                        <td><span class="badge bg-primary"><?php echo $log['new_status']; ?></span></td>
                        <td>
                            <?php 
                            // Get admin name
                            $admin_sql = "SELECT first_name, last_name FROM admins WHERE id = " . $log['changed_by'];
                            $admin_result = mysqli_query($conn, $admin_sql);
                            if ($admin_result && mysqli_num_rows($admin_result) > 0) {
                                $admin = mysqli_fetch_assoc($admin_result);
                                echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']);
                            } else {
                                echo 'Unknown';
                            }
                            ?>
                        </td>
                        <td><small class="text-muted"><?php echo htmlspecialchars($log['notes']); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>