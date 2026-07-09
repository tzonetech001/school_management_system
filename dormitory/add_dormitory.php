<?php
// add_dormitory.php - Simple Dormitory Management
session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';
$info = '';

// Get school ID
$admin_id = $_SESSION['admin_id'] ?? 0;
$school_id = $_SESSION['school_id'] ?? 0;

if ($school_id == 0 && $admin_id > 0) {
    $school_sql = "SELECT school_id FROM admins WHERE id = ?";
    $school_stmt = $conn->prepare($school_sql);
    $school_stmt->bind_param("i", $admin_id);
    $school_stmt->execute();
    $school_result = $school_stmt->get_result();
    if ($school_row = $school_result->fetch_assoc()) {
        $school_id = $school_row['school_id'];
        $_SESSION['school_id'] = $school_id;
    }
    $school_stmt->close();
}

// Check permission
$is_super_admin = isset($_SESSION['super_admin_id']);
$has_permission = $is_super_admin;

if (!$has_permission) {
    $user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
    $stmt = $conn->prepare($user_roles_sql);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $user_roles_result = $stmt->get_result();
    while ($row = $user_roles_result->fetch_assoc()) {
        if (in_array($row['role_id'], [1, 2, 7])) {
            $has_permission = true;
            break;
        }
    }
    $stmt->close();
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission.";
    header("Location: ../404.php");
    exit();
}

// ==================== HANDLE ADD DORMITORY ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_dormitory'])) {
    $dorm_name = trim($_POST['dorm_name']);
    $dorm_type = $_POST['dorm_type'];
    $rooms_count = intval($_POST['rooms_count']);
    $capacity_per_room = intval($_POST['capacity_per_room']);
    $description = trim($_POST['description'] ?? '');
    
    if (empty($dorm_name) || empty($dorm_type) || $rooms_count < 1 || $capacity_per_room < 1) {
        $error = "All fields are required.";
    } else {
        // Check if dormitory already exists
        $check_sql = "SELECT id FROM dormitories WHERE dorm_name = ? AND school_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $dorm_name, $school_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Dormitory '$dorm_name' already exists.";
        } else {
            $total_capacity = $rooms_count * $capacity_per_room;
            
            // Insert dormitory
            $insert_sql = "INSERT INTO dormitories (dorm_name, dorm_type, rooms_count, capacity_per_room, total_capacity, description, status, school_id) 
                           VALUES (?, ?, ?, ?, ?, ?, 'Active', ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ssiiisi", $dorm_name, $dorm_type, $rooms_count, $capacity_per_room, $total_capacity, $description, $school_id);
            
            if ($insert_stmt->execute()) {
                $dormitory_id = $insert_stmt->insert_id;
                
                // Create rooms
                $rooms_created = 0;
                for ($i = 1; $i <= $rooms_count; $i++) {
                    $letter = ($i <= 10) ? 'A' : chr(ord('A') + floor(($i - 1) / 10));
                    $number = ($i <= 10) ? $i : (($i - 1) % 10) + 1;
                    $room_label = $letter . $number;
                    
                    $room_sql = "INSERT INTO dormitory_rooms (dormitory_id, room_number, room_label, capacity, school_id) 
                                 VALUES (?, ?, ?, ?, ?)";
                    $room_stmt = $conn->prepare($room_sql);
                    $room_stmt->bind_param("issii", $dormitory_id, $room_label, $room_label, $capacity_per_room, $school_id);
                    
                    if ($room_stmt->execute()) {
                        $rooms_created++;
                    }
                    $room_stmt->close();
                }
                
                $success = "Dormitory '$dorm_name' added successfully with $rooms_created rooms.";
            } else {
                $error = "Failed to add dormitory: " . $conn->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// ==================== HANDLE DELETE DORMITORY ====================
if (isset($_GET['delete'])) {
    $dorm_id = intval($_GET['delete']);
    
    // Check if has students
    $check_sql = "SELECT COUNT(*) as count FROM student_dormitory WHERE dormitory_id = ? AND status = 'Active'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $dorm_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_row = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if ($check_row['count'] > 0) {
        $error = "Cannot delete - has " . $check_row['count'] . " active students.";
    } else {
        // Delete rooms first (cascade will handle it, but explicit is safer)
        $del_rooms_sql = "DELETE FROM dormitory_rooms WHERE dormitory_id = ?";
        $del_rooms_stmt = $conn->prepare($del_rooms_sql);
        $del_rooms_stmt->bind_param("i", $dorm_id);
        $del_rooms_stmt->execute();
        $del_rooms_stmt->close();
        
        // Delete dormitory
        $del_sql = "DELETE FROM dormitories WHERE id = ? AND school_id = ?";
        $del_stmt = $conn->prepare($del_sql);
        $del_stmt->bind_param("ii", $dorm_id, $school_id);
        
        if ($del_stmt->execute()) {
            $success = "Dormitory deleted successfully.";
        } else {
            $error = "Failed to delete dormitory.";
        }
        $del_stmt->close();
    }
}

// Get all dormitories for this school
$dormitories = [];
$sql = "SELECT * FROM dormitories WHERE school_id = ? ORDER BY dorm_type, dorm_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $dormitories[] = $row;
}
$stmt->close();

// Get school name
$school_name = "School";
if ($school_id > 0) {
    $school_sql = "SELECT school_name FROM schools WHERE id = ?";
    $school_stmt = $conn->prepare($school_sql);
    $school_stmt->bind_param("i", $school_id);
    $school_stmt->execute();
    $school_result = $school_stmt->get_result();
    if ($school_row = $school_result->fetch_assoc()) {
        $school_name = $school_row['school_name'];
    }
    $school_stmt->close();
}

// Calculate statistics
$total_capacity = array_sum(array_column($dormitories, 'total_capacity'));
$male_count = count(array_filter($dormitories, function($d) { return $d['dorm_type'] == 'Male'; }));
$female_count = count(array_filter($dormitories, function($d) { return $d['dorm_type'] == 'Female'; }));
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">
                <i class="fas fa-building me-2"></i>Dormitory Management
                <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($school_name); ?></span>
            </h2>
            <div>
                <a href="dormitory.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Main
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-building" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo count($dormitories); ?></h3>
                    <p>Total Dormitories</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-male" style="color: #007bff;"></i>
                    </div>
                    <h3><?php echo $male_count; ?></h3>
                    <p>Male Dormitories</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-female" style="color: #e83e8c;"></i>
                    </div>
                    <h3><?php echo $female_count; ?></h3>
                    <p>Female Dormitories</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-bed" style="color: #28a745;"></i>
                    </div>
                    <h3><?php echo $total_capacity; ?></h3>
                    <p>Total Bed Capacity</p>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($info): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i> <?php echo htmlspecialchars($info); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

      
        <!-- Dormitory List -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <i class="fas fa-list me-2"></i>All Dormitories (<?php echo count($dormitories); ?>)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="dormTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Rooms</th>
                                <th>Capacity</th>
                                <th>Occupancy</th>
                                <th>Available</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dormitories)): ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="text-center py-4">
                                            <i class="fas fa-building fa-3x text-muted mb-3 d-block"></i>
                                            <h4 class="text-muted">No Dormitories Found</h4>
                                            <p class="text-muted">Add your first dormitory using the form above.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dormitories as $index => $dorm): 
                                    $available = $dorm['total_capacity'] - $dorm['current_occupancy'];
                                    $occupancy_percent = $dorm['total_capacity'] > 0 ? 
                                        round(($dorm['current_occupancy'] / $dorm['total_capacity']) * 100) : 0;
                                    $bar_color = $occupancy_percent >= 90 ? 'danger' : 
                                                ($occupancy_percent >= 70 ? 'warning' : 'success');
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($dorm['dorm_name']); ?></strong>
                                        <?php if (!empty($dorm['description'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($dorm['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $dorm['dorm_type'] == 'Male' ? 'bg-primary' : 'bg-pink'; ?>">
                                            <?php echo $dorm['dorm_type']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $dorm['rooms_count']; ?></td>
                                    <td><?php echo $dorm['total_capacity']; ?></td>
                                    <td>
                                        <?php echo $dorm['current_occupancy']; ?>
                                        <div class="progress mt-1" style="height: 4px;">
                                            <div class="progress-bar bg-<?php echo $bar_color; ?>" 
                                                 style="width: <?php echo $occupancy_percent; ?>%;">
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $available > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $available; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $dorm['status'] == 'Active' ? 'bg-success' : 
                                                ($dorm['status'] == 'Full' ? 'bg-warning' : 
                                                ($dorm['status'] == 'Maintenance' ? 'bg-info' : 'bg-secondary')); 
                                        ?>">
                                            <?php echo $dorm['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            
                                            <a href="add_dormitory.php?delete=<?php echo $dorm['id']; ?>" 
                                               class="btn btn-outline-danger" 
                                               onclick="return confirmDelete('<?php echo htmlspecialchars($dorm['dorm_name']); ?>')"
                                               title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <a href="dormitory.php?view_rooms=<?php echo $dorm['id']; ?>" 
                                               class="btn btn-outline-success" title="View Rooms">
                                                <i class="fas fa-door-open"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer Info -->
        <div class="text-center text-muted small mt-3">
            <i class="fas fa-info-circle me-1"></i>
            Total Dormitories: <?php echo count($dormitories); ?> | 
            Total Capacity: <?php echo $total_capacity; ?> |
            School: <?php echo htmlspecialchars($school_name); ?>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
// Confirm delete
function confirmDelete(name) {
    event.preventDefault();
    const link = event.currentTarget.href;
    
    Swal.fire({
        title: 'Delete Dormitory?',
        html: `Are you sure you want to delete <strong>"${name}"</strong>?<br><br>
               <span class="text-danger">⚠️ This will also delete all rooms.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = link;
        }
    });
    return false;
}

// Show success/error messages with SweetAlert2
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($success): ?>
        Swal.fire({
            title: 'Success! 🎉',
            text: '<?php echo addslashes($success); ?>',
            icon: 'success',
            confirmButtonColor: '#3B9DB3',
            timer: 4000,
            timerProgressBar: true
        });
    <?php endif; ?>
    
    <?php if ($error): ?>
        Swal.fire({
            title: 'Error! ❌',
            text: '<?php echo addslashes($error); ?>',
            icon: 'error',
            confirmButtonColor: '#dc3545'
        });
    <?php endif; ?>
    
    <?php if ($info): ?>
        Swal.fire({
            title: 'Information',
            text: '<?php echo addslashes($info); ?>',
            icon: 'info',
            confirmButtonColor: '#17a2b8',
            timer: 3000,
            timerProgressBar: true
        });
    <?php endif; ?>
});

// Form validation
document.getElementById('dormForm').addEventListener('submit', function(e) {
    const name = this.querySelector('input[name="dorm_name"]').value.trim();
    const type = this.querySelector('select[name="dorm_type"]').value;
    const rooms = this.querySelector('input[name="rooms_count"]').value;
    const capacity = this.querySelector('input[name="capacity_per_room"]').value;
    
    let errors = [];
    
    if (!name) errors.push("Please enter a dormitory name.");
    if (!type) errors.push("Please select a dormitory type.");
    if (!rooms || parseInt(rooms) < 1) errors.push("Please enter a valid number of rooms (minimum 1).");
    if (!capacity || parseInt(capacity) < 1) errors.push("Please enter a valid capacity per room (minimum 1).");
    
    if (errors.length > 0) {
        e.preventDefault();
        Swal.fire({
            title: 'Validation Error',
            html: errors.map(e => `• ${e}`).join('<br>'),
            icon: 'warning',
            confirmButtonColor: '#3B9DB3'
        });
        return false;
    }
    
    // Show loading state
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Adding...';
    btn.disabled = true;
    
    return true;
});

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>

<style>
/* Additional styles for add_dormitory.php */
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
}

.stats-card.simple-card p {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
    font-weight: 500;
}

.bg-pink {
    background-color: #e83e8c !important;
    color: white !important;
}

.table th {
    background-color: rgba(59, 157, 179, 0.05);
    border-bottom: 2px solid rgba(59, 157, 179, 0.2);
}

.btn-group .btn {
    transition: all 0.2s ease;
}

.btn-group .btn:hover {
    transform: scale(1.05);
}

@media (max-width: 768px) {
    .stats-card.simple-card {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .stats-card.simple-card h3 {
        font-size: 1.5rem;
    }
    
    .btn-group {
        flex-wrap: wrap;
        gap: 3px;
    }
    
    .btn-group .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
}
</style>

<?php include '../controller/footer.php'; ?>