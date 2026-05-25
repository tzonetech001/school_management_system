<?php
// candidates/equipment.php - Student Equipment Page
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
$student_gender = $student['sex'];

// Get equipment information
$equipment_sql = "SELECT * FROM student_equipment WHERE student_id = $student_id";
$equipment_result = mysqli_query($conn, $equipment_sql);

// If no record exists, create one
if (mysqli_num_rows($equipment_result) == 0) {
    $insert_sql = "INSERT INTO student_equipment (student_id) VALUES ($student_id)";
    mysqli_query($conn, $insert_sql);
    
    // Fetch the new record
    $equipment_result = mysqli_query($conn, $equipment_sql);
}

$equipment = mysqli_fetch_assoc($equipment_result);

// Get maintenance assignments (borrowed items)
$maintenance_sql = "SELECT 
    ma.*,
    mi.item_code,
    mi.item_type,
    mi.description as item_description,
    a.first_name as admin_first,
    a.last_name as admin_last
FROM maintenance_assignments ma
JOIN maintenance_items mi ON ma.item_id = mi.id
LEFT JOIN admins a ON ma.assigned_by = a.id
WHERE ma.student_id = $student_id
ORDER BY 
    CASE ma.status 
        WHEN 'active' THEN 1 
        WHEN 'returned' THEN 2 
        ELSE 3 
    END,
    ma.assigned_date DESC";

$maintenance_result = mysqli_query($conn, $maintenance_sql);
$maintenance_items = [];
if ($maintenance_result && mysqli_num_rows($maintenance_result) > 0) {
    while ($row = mysqli_fetch_assoc($maintenance_result)) {
        $maintenance_items[] = $row;
    }
}

// Calculate equipment statistics
$total_items = $equipment['total_equipment_count'] ?? 0;
$required_items = 12; // Total required items (including 2 buckets)
$completion_percentage = $required_items > 0 ? min(round(($total_items / $required_items) * 100, 1), 100) : 0;

// Status colors and messages
$status_colors = [
    'Complete' => 'success',
    'Incomplete' => 'warning',
    'None' => 'danger'
];

$status_messages = [
    'Complete' => 'You have all required equipment. Great job!',
    'Incomplete' => 'You are missing some equipment. Please check the list below.',
    'None' => 'No equipment has been issued to you yet. Please report to the store.'
];

$status_color = $status_colors[$equipment['equipment_status']] ?? 'secondary';
$status_message = $status_messages[$equipment['equipment_status']] ?? 'Equipment status unknown';

// Define equipment based on gender
$male_equipment = [
    'leam' => ['label' => 'Leam  ', 'icon' => 'fas fa-tools', 'required' => 4],
    'hoe' => ['label' => 'Hoe (Korede)', 'icon' => 'fas fa-tools', 'required' => 1],
    'lek' => ['label' => 'Lek (Kijiko)', 'icon' => 'fas fa-trowel', 'required' => 1],
    'slasher' => ['label' => 'Slasher (Panga)', 'icon' => 'fas fa-cut', 'required' => 1],
    'soft_broom' => ['label' => 'Soft Broom', 'icon' => 'fas fa-broom', 'required' => 1],
    'hard_broom' => ['label' => 'Hard Broom', 'icon' => 'fas fa-broom', 'required' => 1],
    'chelewa_broom' => ['label' => 'Chelewa Broom', 'icon' => 'fas fa-broom', 'required' => 1],
    'bucket' => ['label' => 'Buckets', 'icon' => 'fas fa-fill-drip', 'required' => 2]
];

$female_equipment = [
    'leam' => ['label' => 'Leam  ', 'icon' => 'fas fa-tools', 'required' => 4],
    'hoe' => ['label' => 'Hoe (Korede)', 'icon' => 'fas fa-tools', 'required' => 1],
    'machet' => ['label' => 'Machete (Panga)', 'icon' => 'fas fa-cut', 'required' => 1],
    'slasher' => ['label' => 'Slasher (Kisu)', 'icon' => 'fas fa-cut', 'required' => 1],
    'soft_broom' => ['label' => 'Soft Broom', 'icon' => 'fas fa-broom', 'required' => 1],
    'hard_broom' => ['label' => 'Hard Broom', 'icon' => 'fas fa-broom', 'required' => 1],
    'chelewa_broom' => ['label' => 'Chelewa Broom', 'icon' => 'fas fa-broom', 'required' => 1],
    'bucket' => ['label' => 'Buckets', 'icon' => 'fas fa-fill-drip', 'required' => 2]
];

$equipment_list = ($student_gender == 'Male') ? $male_equipment : $female_equipment;

// Get equipment updates history
$updates_sql = "SELECT 
    eu.*,
    a.first_name as admin_first,
    a.last_name as admin_last
FROM equipment_updates eu
LEFT JOIN admins a ON eu.updated_by = a.id
WHERE eu.student_id = $student_id
ORDER BY eu.created_at DESC
LIMIT 20";

$updates_result = mysqli_query($conn, $updates_sql);
$updates = [];
if ($updates_result && mysqli_num_rows($updates_result) > 0) {
    while ($row = mysqli_fetch_assoc($updates_result)) {
        $updates[] = $row;
    }
}
?>

<?php include 'header.php'; ?>
<?php include 'sidebar_student.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">
                <i class="fas fa-toolbox me-2" style="color: #fd7e14;"></i>
                My Equipment
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
                                    <i class="fas fa-<?php echo $equipment['equipment_status'] == 'Complete' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                                    Status: <?php echo $equipment['equipment_status']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Equipment Card -->
        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card h-100">
                    <div class="card-header" style="background-color: #fd7e14; color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-list me-2"></i>
                            Equipment List - <?php echo $student_gender; ?> Student
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Progress Section -->
                        <div class="text-center mb-4">
                            <h2 class="display-4 fw-bold text-warning"><?php echo $total_items; ?>/<?php echo $required_items; ?></h2>
                            <p class="text-muted">items issued</p>
                            
                            <div class="progress mb-3" style="height: 30px;">
                                <div class="progress-bar bg-<?php echo $status_color; ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo $completion_percentage; ?>%; font-size: 14px; font-weight: bold;"
                                     aria-valuenow="<?php echo $completion_percentage; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    <?php echo $completion_percentage; ?>% Complete
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Message -->
                        <div class="alert alert-<?php echo $status_color; ?> mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <?php echo $status_message; ?>
                        </div>
                        
                        <!-- Equipment Grid -->
                        <div class="row">
                            <?php foreach ($equipment_list as $key => $item): 
                                $value = $equipment[$key] ?? 0;
                                $is_complete = ($key == 'bucket') ? $value >= $item['required'] : $value >= $item['required'];
                                $item_status = $is_complete ? 'success' : ($value > 0 ? 'warning' : 'danger');
                            ?>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center p-3 border rounded">
                                    <div class="equipment-icon me-3">
                                        <i class="<?php echo $item['icon']; ?> fa-2x text-<?php echo $item_status; ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo $item['label']; ?></h6>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted small">
                                                Required: <?php echo $item['required']; ?> 
                                                <?php echo $key == 'bucket' ? '(minimum 2)' : ''; ?>
                                            </span>
                                            <span class="badge bg-<?php echo $item_status; ?>">
                                                <?php echo $key == 'bucket' ? $value : ($value ? 'Yes' : 'No'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Last Updated -->
                        <?php if ($equipment['equipment_last_updated']): ?>
                            <div class="text-end mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    Last updated: <?php echo date('F j, Y', strtotime($equipment['equipment_last_updated'])); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Info & Rules -->
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header" style="background-color: #6f42c1; color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Equipment Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6><i class="fas fa-check-circle text-success me-2"></i>Required Equipment</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-tools me-2 text-primary"></i>4 Leam  </li>
                                <li class="mb-2"><i class="fas fa-tools me-2 text-primary"></i>1 Hoe (jembe)</li>
                                <?php if ($student_gender == 'Male'): ?>
                                    <li class="mb-2"><i class="fas fa-trowel me-2 text-primary"></i>1 Lek (Kijiko)</li>
                                <?php else: ?>
                                    <li class="mb-2"><i class="fas fa-cut me-2 text-primary"></i>1 Machete (Panga)</li>
                                <?php endif; ?>
                                <li class="mb-2"><i class="fas fa-cut me-2 text-primary"></i>1 Slasher</li>
                                <li class="mb-2"><i class="fas fa-broom me-2 text-primary"></i>1 Soft Broom</li>
                                <li class="mb-2"><i class="fas fa-broom me-2 text-primary"></i>1 Hard Broom</li>
                                <li class="mb-2"><i class="fas fa-broom me-2 text-primary"></i>1 Chelewa Broom</li>
                                <li class="mb-2"><i class="fas fa-fill-drip me-2 text-primary"></i>2 Buckets (minimum)</li>
                            </ul>
                        </div>
                        
                        <div class="mb-4">
                            <h6><i class="fas fa-exclamation-triangle text-warning me-2"></i>Important Rules</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-circle text-danger me-2" style="font-size: 8px;"></i>Equipment must be kept clean and in good condition</li>
                                <li class="mb-2"><i class="fas fa-circle text-danger me-2" style="font-size: 8px;"></i>Lost items must be reported immediately</li>
                                <li class="mb-2"><i class="fas fa-circle text-danger me-2" style="font-size: 8px;"></i>Equipment will be collected at end of academic year</li>
                                <li class="mb-2"><i class="fas fa-circle text-danger me-2" style="font-size: 8px;"></i>Damaged items should be reported for replacement</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-tools me-2"></i>
                            <strong>Store Location:</strong> School Equipment Store<br>
                            <small>Open Monday-Friday, 9:00 AM - 4:00 PM</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Borrowed Items Section (Maintenance) -->
        <?php if (!empty($maintenance_items)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header" style="background-color: #17a2b8; color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-hand-holding me-2"></i>
                            Borrowed Items (Maintenance)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Item Code</th>
                                        <th>Item Type</th>
                                        <th>Description</th>
                                        <th>Assigned Date</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Return Date</th>
                                        <th>Condition</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($maintenance_items as $index => $item): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['item_code']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo ucfirst($item['item_type']); ?></span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($item['item_description'] ?: 'No description'); ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($item['assigned_date'])); ?></td>
                                        <td>
                                            <?php if ($item['due_date']): ?>
                                                <?php 
                                                $due = strtotime($item['due_date']);
                                                $today = time();
                                                $days = ceil(($due - $today) / (60 * 60 * 24));
                                                $due_color = $days < 0 ? 'danger' : ($days < 3 ? 'warning' : 'success');
                                                ?>
                                                <span class="badge bg-<?php echo $due_color; ?>">
                                                    <?php echo date('d/m/Y', $due); ?>
                                                    <?php if ($item['status'] == 'active'): ?>
                                                        (<?php echo $days < 0 ? 'Overdue' : "$days days left"; ?>)
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item['status'] == 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php elseif ($item['status'] == 'returned'): ?>
                                                <span class="badge bg-secondary">Returned</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning"><?php echo ucfirst($item['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item['return_date']): ?>
                                                <?php echo date('d/m/Y', strtotime($item['return_date'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item['return_condition']): ?>
                                                <span class="badge bg-<?php echo $item['return_condition'] == 'good' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($item['return_condition']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Equipment Updates History -->
        <?php if (!empty($updates)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header" style="background-color: #6c757d; color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            Recent Equipment Updates
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Equipment</th>
                                        <th>Action</th>
                                        <th>Quantity</th>
                                        <th>Updated By</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($updates as $update): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($update['created_at'])); ?></td>
                                        <td><?php echo ucwords(str_replace('_', ' ', $update['equipment_type'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $update['action'] == 'Added' ? 'success' : ($update['action'] == 'Removed' ? 'danger' : 'info'); ?>">
                                                <?php echo $update['action']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $update['quantity']; ?></td>
                                        <td>
                                            <?php if (!empty($update['admin_first'])): ?>
                                                <?php echo htmlspecialchars($update['admin_first'] . ' ' . $update['admin_last']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">System</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($update['notes'])): ?>
                                                <small><?php echo htmlspecialchars($update['notes']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Responsibility Confirmation -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="responsibilityCheck" onchange="toggleAcknowledgement()">
                            <label class="form-check-label" for="responsibilityCheck">
                                I acknowledge that I am responsible for the equipment issued to me and will return it in good condition at the end of the academic year.
                            </label>
                        </div>
                        
                        <div id="acknowledgementMessage" class="alert alert-success mt-3" style="display: none;">
                            <i class="fas fa-check-circle me-2"></i>
                            Thank you for acknowledging your responsibility. Please take good care of your equipment.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.equipment-icon {
    width: 40px;
    text-align: center;
}

.progress-bar {
    transition: width 1s ease;
}

.display-4 {
    font-size: 3.5rem;
    font-weight: 600;
    color: #fd7e14;
}

.border {
    transition: all 0.3s ease;
}

.border:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

@media (max-width: 768px) {
    .display-4 {
        font-size: 2.5rem;
    }
    
    .equipment-icon {
        width: 30px;
    }
    
    .equipment-icon i {
        font-size: 1.5rem !important;
    }
}
</style>

<script>
function toggleAcknowledgement() {
    const check = document.getElementById('responsibilityCheck');
    const message = document.getElementById('acknowledgementMessage');
    
    if (check.checked) {
        message.style.display = 'block';
        
        // Save acknowledgment to localStorage
        localStorage.setItem('equipmentAcknowledged_' + <?php echo $student_id; ?>, 'true');
        localStorage.setItem('equipmentAcknowledgedDate_' + <?php echo $student_id; ?>, new Date().toISOString());
    } else {
        message.style.display = 'none';
        localStorage.removeItem('equipmentAcknowledged_' + <?php echo $student_id; ?>);
    }
}

// Check if previously acknowledged
document.addEventListener('DOMContentLoaded', function() {
    const acknowledged = localStorage.getItem('equipmentAcknowledged_' + <?php echo $student_id; ?>);
    if (acknowledged === 'true') {
        document.getElementById('responsibilityCheck').checked = true;
        document.getElementById('acknowledgementMessage').style.display = 'block';
    }
});
</script>

<?php include '../controller/footer.php'; ?>