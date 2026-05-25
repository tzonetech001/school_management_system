<?php
// candidates/my_dormitory.php - Student Dormitory View
session_start();
require_once '../controller/db_connect.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Get student's dormitory assignment
$assignment_sql = "SELECT 
    sd.*,
    s.first_name,
    s.last_name,
    s.index_number,
    s.class,
    s.combination,
    s.sex,
    d.dorm_name,
    d.dorm_type,
    d.description as dorm_description,
    dr.room_number,
    dr.room_label,
    dr.capacity as room_capacity,
    dr.current_occupancy as room_occupancy,
    (SELECT COUNT(*) FROM student_dormitory sd2 
     WHERE sd2.room_id = dr.id AND sd2.status = 'Active') as total_in_room
FROM student_dormitory sd
JOIN students s ON sd.student_id = s.id
JOIN dormitories d ON sd.dormitory_id = d.id
JOIN dormitory_rooms dr ON sd.room_id = dr.id
WHERE sd.student_id = $student_id 
AND sd.status = 'Active'
LIMIT 1";

$assignment_result = mysqli_query($conn, $assignment_sql);
$has_assignment = mysqli_num_rows($assignment_result) > 0;
$assignment = $has_assignment ? mysqli_fetch_assoc($assignment_result) : null;

// Get roommates if student has assignment
$roommates = [];
if ($has_assignment && $assignment['room_id']) {
    $roommates_sql = "SELECT 
        s.first_name,
        s.last_name,
        s.index_number,
        s.class,
        s.combination,
        sd.bed_number
    FROM student_dormitory sd
    JOIN students s ON sd.student_id = s.id
    WHERE sd.room_id = {$assignment['room_id']} 
    AND sd.status = 'Active'
    AND sd.student_id != $student_id
    ORDER BY s.first_name, s.last_name";
    
    $roommates_result = mysqli_query($conn, $roommates_sql);
    if ($roommates_result && mysqli_num_rows($roommates_result) > 0) {
        while ($row = mysqli_fetch_assoc($roommates_result)) {
            $roommates[] = $row;
        }
    }
}

// Get dormitory statistics
$dorm_stats = null;
if ($has_assignment && $assignment['dormitory_id']) {
    $stats_sql = "SELECT 
        COUNT(DISTINCT dr.id) as total_rooms,
        SUM(dr.capacity) as total_capacity,
        COUNT(DISTINCT sd.id) as total_occupancy,
        (SUM(dr.capacity) - COUNT(DISTINCT sd.id)) as available_beds
    FROM dormitories d
    LEFT JOIN dormitory_rooms dr ON d.id = dr.dormitory_id
    LEFT JOIN student_dormitory sd ON dr.id = sd.room_id AND sd.status = 'Active'
    WHERE d.id = {$assignment['dormitory_id']}
    GROUP BY d.id";
    
    $stats_result = mysqli_query($conn, $stats_sql);
    if ($stats_result && mysqli_num_rows($stats_result) > 0) {
        $dorm_stats = mysqli_fetch_assoc($stats_result);
    }
}

// Calculate occupancy percentage
$occupancy_percentage = 0;
if ($has_assignment && $assignment['room_capacity'] > 0) {
    $occupancy_percentage = round(($assignment['room_occupancy'] / $assignment['room_capacity']) * 100, 1);
}

// Get dormitory color based on type
$dorm_color = ($assignment && $assignment['dorm_type'] == 'Male') ? '#007bff' : '#e83e8c';
?>

<?php include 'header.php'; ?>
<?php include 'sidebar_student.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">
                <i class="fas fa-bed me-2" style="color: <?php echo $dorm_color; ?>;"></i>
                My Dormitory Information
            </h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>

        <?php if (!$has_assignment): ?>
            <!-- No Assignment Card -->
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card text-center">
                        <div class="card-body py-5">
                            <div class="mb-4">
                                <i class="fas fa-bed fa-5x text-muted"></i>
                            </div>
                            <h4 class="mb-3">No Dormitory Assigned</h4>
                            <p class="text-muted mb-4">
                                You have not been assigned to any dormitory yet. 
                                Please contact the school administration for dormitory allocation.
                            </p>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> Dormitory assignments are managed by the school administration.
                                If you believe this is an error, please visit the academic office.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Dormitory Information Cards -->
            <div class="row">
                <!-- Main Dormitory Card -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header" style="background-color: <?php echo $dorm_color; ?>; color: white;">
                            <h5 class="mb-0">
                                <i class="fas fa-<?php echo $assignment['dorm_type'] == 'Male' ? 'male' : 'female'; ?> me-2"></i>
                                My Dormitory: <?php echo htmlspecialchars($assignment['dorm_name']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <div class="d-inline-block p-3 rounded-circle" style="background-color: <?php echo $dorm_color; ?>20;">
                                    <i class="fas fa-bed fa-3x" style="color: <?php echo $dorm_color; ?>;"></i>
                                </div>
                                <h3 class="mt-3"><?php echo htmlspecialchars($assignment['dorm_name']); ?> Dormitory</h3>
                                <p class="text-muted"><?php echo htmlspecialchars($assignment['dorm_type']); ?> Students</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Dormitory Type</small>
                                        <strong>
                                            <i class="fas fa-<?php echo $assignment['dorm_type'] == 'Male' ? 'male' : 'female'; ?> me-1"></i>
                                            <?php echo $assignment['dorm_type']; ?>
                                        </strong>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Total Capacity</small>
                                        <strong><?php echo $dorm_stats['total_capacity'] ?? 0; ?> students</strong>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Current Occupancy</small>
                                        <strong><?php echo $dorm_stats['total_occupancy'] ?? 0; ?> students</strong>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Available Beds</small>
                                        <strong class="text-success"><?php echo $dorm_stats['available_beds'] ?? 0; ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($assignment['dorm_description'])): ?>
                                <div class="mt-3">
                                    <small class="text-muted d-block mb-2">Description</small>
                                    <p><?php echo nl2br(htmlspecialchars($assignment['dorm_description'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Room Information Card -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header" style="background-color: #3B9DB3; color: white;">
                            <h5 class="mb-0">
                                <i class="fas fa-door-open me-2"></i>
                                My Room Details
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <div class="d-inline-block p-3 rounded-circle" style="background-color: #3B9DB320;">
                                    <i class="fas fa-door-open fa-3x" style="color: #3B9DB3;"></i>
                                </div>
                                <h3 class="mt-3">Room <?php echo htmlspecialchars($assignment['room_number']); ?></h3>
                                <p class="text-muted"><?php echo htmlspecialchars($assignment['room_label']); ?></p>
                            </div>
                            
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Room Number</small>
                                        <strong><?php echo htmlspecialchars($assignment['room_number']); ?></strong>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Room Label</small>
                                        <strong><?php echo htmlspecialchars($assignment['room_label']); ?></strong>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">My Bed Number</small>
                                        <?php if (!empty($assignment['bed_number'])): ?>
                                            <strong class="text-primary">
                                                <i class="fas fa-bed me-1"></i><?php echo htmlspecialchars($assignment['bed_number']); ?>
                                            </strong>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Room Capacity</small>
                                        <strong><?php echo $assignment['room_capacity']; ?> students</strong>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Room Occupancy Progress -->
                            <div class="mt-4">
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Room Occupancy</small>
                                    <small><?php echo $assignment['room_occupancy']; ?>/<?php echo $assignment['room_capacity']; ?> students</small>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?php echo $occupancy_percentage; ?>%; background-color: <?php echo $dorm_color; ?>;" 
                                         aria-valuenow="<?php echo $assignment['room_occupancy']; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="<?php echo $assignment['room_capacity']; ?>">
                                    </div>
                                </div>
                                <small class="text-muted d-block text-end mt-1">
                                    <?php echo $assignment['room_capacity'] - $assignment['room_occupancy']; ?> spaces available
                                </small>
                            </div>
                            
                            <!-- Bed Location Info -->
                            <?php if (!empty($assignment['bed_number'])): ?>
                                <div class="alert alert-success mt-4 mb-0">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Your bed is ready!</strong> You are assigned to 
                                    <strong><?php echo htmlspecialchars($assignment['bed_number']); ?></strong> in this room.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning mt-4 mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Bed number not assigned.</strong> Please check with the dormitory master for your specific bed assignment.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Roommates Section -->
            <?php if (!empty($roommates)): ?>
            <div class="row mt-2">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header" style="background-color: #28a745; color: white;">
                            <h5 class="mb-0">
                                <i class="fas fa-users me-2"></i>
                                My Roommates (<?php echo count($roommates); ?>)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($roommates as $index => $roommate): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="d-flex align-items-center p-3 border rounded">
                                        <div class="avatar-circle me-3" 
                                             style="background-color: <?php echo $dorm_color; ?>20; width: 50px; height: 50px;">
                                            <i class="fas fa-user" style="color: <?php echo $dorm_color; ?>;"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($roommate['first_name'] . ' ' . $roommate['last_name']); ?></strong>
                                            <div class="text-muted small">
                                                <?php echo htmlspecialchars($roommate['class'] . ' - ' . $roommate['combination']); ?>
                                            </div>
                                            <?php if (!empty($roommate['bed_number'])): ?>
                                                <small class="text-primary">
                                                    <i class="fas fa-bed me-1"></i>Bed: <?php echo htmlspecialchars($roommate['bed_number']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Dormitory Rules & Guidelines -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header" style="background-color: #ffc107; color: #212529;">
                            <h5 class="mb-0">
                                <i class="fas fa-clipboard-list me-2"></i>
                                Dormitory Guidelines
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-clock text-warning me-2"></i>Dormitory Rules</h6>
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Keep your bed area clean and tidy</li>
                                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Respect quiet hours (10:00 PM - 5:00 AM)</li>
                                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>No visitors allowed in dormitories</li>
                                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Report any maintenance issues to dorm master</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-exclamation-triangle text-warning me-2"></i>Important Contacts</h6>
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="fas fa-user-tie text-primary me-2"></i>Dormitory Master: Visit academic office</li>
                                        <li class="mb-2"><i class="fas fa-tools text-primary me-2"></i>Maintenance: Report to office</li>
                                        <li class="mb-2"><i class="fas fa-first-aid text-primary me-2"></i>Health Issues: School dispensary</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning mt-3 mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> For any dormitory-related issues or bed changes, please contact the dormitory master or academic office.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Info Cards -->
            <div class="row mt-4">
                <div class="col-md-4 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-door-open fa-2x text-primary mb-2"></i>
                            <h6>Room Number</h6>
                            <h4><?php echo htmlspecialchars($assignment['room_number']); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-bed fa-2x text-success mb-2"></i>
                            <h6>Bed Number</h6>
                            <h4><?php echo !empty($assignment['bed_number']) ? htmlspecialchars($assignment['bed_number']) : 'Not Set'; ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-users fa-2x text-info mb-2"></i>
                            <h6>Roommates</h6>
                            <h4><?php echo count($roommates); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Student Dormitory Page Specific Styles */
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 6px 15px rgba(0,0,0,0.1);
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
    padding: 1rem 1.5rem;
}

.progress {
    border-radius: 10px;
    background-color: #e9ecef;
}

.progress-bar {
    border-radius: 10px;
    transition: width 0.6s ease;
}

.list-unstyled li {
    padding: 5px 0;
    border-bottom: 1px dashed #e0e0e0;
}

.list-unstyled li:last-child {
    border-bottom: none;
}

@media (max-width: 768px) {
    .avatar-circle {
        width: 35px;
        height: 35px;
    }
    
    .card-header h5 {
        font-size: 1rem;
    }
    
    .display-4 {
        font-size: 2rem;
    }
}
</style>

<?php include '../controller/footer.php'; ?>