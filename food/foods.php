<?php
// foods.php - Food Stock Management with Compact Stats Cards
session_start();
require_once '../controller/db_connect.php';

$error = '';
$success = '';

// Check if user has permission (Head Master or Second Master only)
$admin_id = $_SESSION['admin_id'] ?? 0;

// Get current user's roles
$user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}

// Check if user has Head Master (1) or Second Master (2) role
$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2 || $role_id == 11) { // Head Master or Second Master
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to view staff members.";
    header("Location:  ../404.php");
    exit();
}


// Get all roles from database (excluding Super Admin if it exists)
$roles = [];
$roles_sql = "SELECT * FROM admin_roles WHERE role_name != 'Super Admin' ORDER BY role_name";
$roles_result = mysqli_query($conn, $roles_sql);
if ($roles_result && mysqli_num_rows($roles_result) > 0) {
    while ($row = mysqli_fetch_assoc($roles_result)) {
        $roles[] = $row;
    }
}


// Initialize variables
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle add food
    if (isset($_POST['add_food'])) {
        $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
        $quantity = floatval($_POST['quantity']);
        $unit = mysqli_real_escape_string($conn, $_POST['unit']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $date_added = !empty($_POST['date_added']) ? mysqli_real_escape_string($conn, $_POST['date_added']) : date('Y-m-d');
        
        // Validate inputs
        if (empty($item_name) || $quantity <= 0 || empty($unit)) {
            $_SESSION['error'] = "Please fill all required fields correctly!";
            header("Location: foods.php");
            exit();
        } else {
            // Insert into database
            $sql = "INSERT INTO food_stock (item_name, quantity, unit, date_added, description) 
                    VALUES ('$item_name', $quantity, '$unit', '$date_added', '$description')";
            
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Food item added successfully!";
                header("Location: foods.php");
                exit();
            } else {
                $_SESSION['error'] = "Error adding food item: " . mysqli_error($conn);
                header("Location: foods.php");
                exit();
            }
        }
    }
    
    // Handle update
    if (isset($_POST['update_food'])) {
        $id = intval($_POST['id']);
        $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
        $quantity = floatval($_POST['quantity']);
        $unit = mysqli_real_escape_string($conn, $_POST['unit']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        $sql = "UPDATE food_stock SET 
                item_name = '$item_name',
                quantity = $quantity,
                unit = '$unit',
                description = '$description',
                updated_at = NOW()
                WHERE id = $id";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Food item updated successfully!";
            header("Location: foods.php");
            exit();
        } else {
            $_SESSION['error'] = "Error updating food item: " . mysqli_error($conn);
            header("Location: foods.php");
            exit();
        }
    }
    
    // Handle quantity adjustment
    if (isset($_POST['adjust_quantity'])) {
        $id = intval($_POST['id']);
        $adjust_type = mysqli_real_escape_string($conn, $_POST['adjust_type']);
        $adjust_qty = floatval($_POST['quantity']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        
        // Get current quantity
        $current_sql = "SELECT quantity FROM food_stock WHERE id = $id";
        $current_result = mysqli_query($conn, $current_sql);
        
        if (!$current_result) {
            $_SESSION['error'] = "Error fetching current quantity: " . mysqli_error($conn);
            header("Location: foods.php");
            exit();
        } else {
            $current_data = mysqli_fetch_assoc($current_result);
            $current_qty = $current_data['quantity'];
            
            // Calculate new quantity
            if ($adjust_type === 'add') {
                $new_qty = $current_qty + $adjust_qty;
            } else {
                $new_qty = $current_qty - $adjust_qty;
                if ($new_qty < 0) $new_qty = 0;
            }
            
            // Update quantity
            $update_sql = "UPDATE food_stock SET quantity = $new_qty, updated_at = NOW() WHERE id = $id";
            
            if (mysqli_query($conn, $update_sql)) {
                // Log the adjustment
                $admin_id = $_SESSION['admin_id'] ?? 1;
                $log_sql = "INSERT INTO food_stock_history (food_id, old_quantity, new_quantity, change_type, reason, changed_by) 
                           VALUES ($id, $current_qty, $new_qty, '$adjust_type', '$reason', $admin_id)";
                mysqli_query($conn, $log_sql);
                
                $_SESSION['success'] = "Quantity adjusted successfully!";
                header("Location: foods.php");
                exit();
            } else {
                $_SESSION['error'] = "Error adjusting quantity: " . mysqli_error($conn);
                header("Location: foods.php");
                exit();
            }
        }
    }
    
    // Handle delete
    if (isset($_POST['delete_food'])) {
        $id = intval($_POST['id']);
        
        $sql = "DELETE FROM food_stock WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Food item deleted successfully!";
            header("Location: foods.php");
            exit();
        } else {
            $_SESSION['error'] = "Error deleting food item: " . mysqli_error($conn);
            header("Location: foods.php");
            exit();
        }
    }
}

// Get all food items
$sql = "SELECT * FROM food_stock ORDER BY 
        CASE status 
            WHEN 'out_of_stock' THEN 1
            WHEN 'low' THEN 2
            ELSE 3
        END,
        item_name ASC";
$result = mysqli_query($conn, $sql);
$food_items = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $food_items[] = $row;
    }
}

// Get summary statistics
$stats_sql = "SELECT 
    COUNT(*) as total_items,
    SUM(quantity) as total_quantity,
    SUM(CASE WHEN status = 'low' THEN 1 ELSE 0 END) as low_stock,
    SUM(CASE WHEN status = 'out_of_stock' THEN 1 ELSE 0 END) as out_of_stock,
    AVG(quantity) as avg_quantity,
    MIN(quantity) as min_quantity,
    MAX(quantity) as max_quantity,
    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_items
    FROM food_stock";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result) ?? [
    'total_items' => 0,
    'total_quantity' => 0,
    'low_stock' => 0,
    'out_of_stock' => 0,
    'avg_quantity' => 0,
    'min_quantity' => 0,
    'max_quantity' => 0,
    'available_items' => 0
];

// Get recent changes
$changes_sql = "SELECT f.item_name, h.* 
                FROM food_stock_history h
                JOIN food_stock f ON h.food_id = f.id
                ORDER BY h.changed_at DESC LIMIT 5";
$changes_result = mysqli_query($conn, $changes_sql);
$recent_changes = [];
if ($changes_result) {
    while ($row = mysqli_fetch_assoc($changes_result)) {
        $recent_changes[] = $row;
    }
}

// Available units
$available_units = ['kg', 'liters', 'bags', 'packets', 'pieces', 'cartons', 'boxes', 'cans', 'bottles', 'sacks'];
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- SweetAlert2 Messages -->
        <?php if ($success): ?>
            <div id="successMessage" data-message="<?php echo htmlspecialchars($success); ?>"></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div id="errorMessage" data-message="<?php echo htmlspecialchars($error); ?>"></div>
        <?php endif; ?>

        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">Food Stock Management</h2>
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" id="actionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog me-2"></i>Actions
                </button>
                <ul class="dropdown-menu" aria-labelledby="actionsDropdown">
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addFoodModal">
                        <i class="fas fa-plus-circle me-2"></i>Add New Food Item
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="export_food.php">
                        <i class="fas fa-file-export me-2"></i>Export Report & Analysis
                    </a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                        <i class="fas fa-edit me-2"></i>Bulk Update
                    </a></li>
                </ul>
            </div>
        </div>

        <!-- Compact Statistics Cards Row - Three cards in smaller size -->
        <div class="row mb-4 compact-stats-row">
            <!-- Card 1: Total Items -->
            <div class="col-md-4 mb-3">
                <div class="compact-stats-card">
                    <div class="compact-stats-icon bg-primary text-white">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div class="compact-stats-content">
                        <div class="compact-stats-number"><?php echo $stats['total_items'] ?? 0; ?></div>
                        <div class="compact-stats-label">Total Items</div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Low Stock Items -->
            <div class="col-md-4 mb-3">
                <div class="compact-stats-card">
                    <div class="compact-stats-icon bg-warning text-white">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="compact-stats-content">
                        <div class="compact-stats-number"><?php echo $stats['low_stock'] ?? 0; ?></div>
                        <div class="compact-stats-label">Low Stock Items</div>
                        <div class="compact-stats-subtext">Needs Replenishment</div>
                    </div>
                </div>
            </div>

            <!-- Card 3: Out of Stock -->
            <div class="col-md-4 mb-3">
                <div class="compact-stats-card">
                    <div class="compact-stats-icon bg-danger text-white">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="compact-stats-content">
                        <div class="compact-stats-number"><?php echo $stats['out_of_stock'] ?? 0; ?></div>
                        <div class="compact-stats-label">Out of Stock</div>
                        <div class="compact-stats-subtext">Urgent Restock Needed</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Statistics (Smaller secondary stats) -->
        <div class="row mb-4 secondary-stats-row">
            <div class="col-md-3 col-6 mb-3">
                <div class="mini-stats-card">
                    <div class="mini-stats-icon">
                        <i class="fas fa-weight-hanging text-success"></i>
                    </div>
                    <div class="mini-stats-info">
                        <div class="mini-stats-number"><?php echo number_format($stats['total_quantity'] ?? 0, 1); ?></div>
                        <div class="mini-stats-label">Total Quantity</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="mini-stats-card">
                    <div class="mini-stats-icon">
                        <i class="fas fa-chart-line text-info"></i>
                    </div>
                    <div class="mini-stats-info">
                        <div class="mini-stats-number"><?php echo number_format($stats['avg_quantity'] ?? 0, 1); ?></div>
                        <div class="mini-stats-label">Average</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="mini-stats-card">
                    <div class="mini-stats-icon">
                        <i class="fas fa-arrow-down text-secondary"></i>
                    </div>
                    <div class="mini-stats-info">
                        <div class="mini-stats-number"><?php echo number_format($stats['min_quantity'] ?? 0, 1); ?></div>
                        <div class="mini-stats-label">Minimum</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="mini-stats-card">
                    <div class="mini-stats-icon">
                        <i class="fas fa-arrow-up text-dark"></i>
                    </div>
                    <div class="mini-stats-info">
                        <div class="mini-stats-number"><?php echo number_format($stats['max_quantity'] ?? 0, 1); ?></div>
                        <div class="mini-stats-label">Maximum</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Changes -->
        <?php if (!empty($recent_changes)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Stock Changes</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Type</th>
                                <th>Old Qty</th>
                                <th>New Qty</th>
                                <th>Difference</th>
                                <th>Reason</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_changes as $change): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($change['item_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $change['change_type'] == 'add' ? 'success' : ($change['change_type'] == 'remove' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($change['change_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($change['old_quantity'], 2); ?></td>
                                <td><?php echo number_format($change['new_quantity'], 2); ?></td>
                                <td>
                                    <?php 
                                    $diff = $change['new_quantity'] - $change['old_quantity'];
                                    $class = $diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : 'text-muted');
                                    ?>
                                    <span class="<?php echo $class; ?>">
                                        <?php echo ($diff > 0 ? '+' : '') . number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($change['reason']); ?></td>
                                <td><?php echo date('H:i', strtotime($change['changed_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Search and Filter Section -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search food items...">
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="available">Available</option>
                            <option value="low">Low Stock</option>
                            <option value="out_of_stock">Out of Stock</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <select id="unitFilter" class="form-select">
                            <option value="">All Units</option>
                            <?php foreach ($available_units as $unit): ?>
                                <option value="<?php echo $unit; ?>"><?php echo ucfirst($unit); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Food Stock Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Food Stock Inventory</h5>
                <div>
                    <button type="button" class="btn btn-light btn-sm me-2" data-bs-toggle="modal" data-bs-target="#addFoodModal">
                        <i class="fas fa-plus me-1"></i>Add New
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="foodTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Item Name</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Status</th>
                                <th>Date Added</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($food_items)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-box-open fa-2x mb-3"></i>
                                            <p>No food items found. Add your first item!</p>
                                            <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addFoodModal">
                                                <i class="fas fa-plus me-2"></i>Add First Item
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($food_items as $index => $item): 
                                    // Determine status color
                                    $status_color = '';
                                    if ($item['status'] == 'available') $status_color = 'success';
                                    if ($item['status'] == 'low') $status_color = 'warning';
                                    if ($item['status'] == 'out_of_stock') $status_color = 'danger';
                                ?>
                                <tr data-item-id="<?php echo $item['id']; ?>">
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="fas fa-utensils text-primary"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                                <?php if (!empty($item['description'])): ?>
                                                <div class="small text-muted"><?php echo htmlspecialchars($item['description']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark"><?php echo number_format($item['quantity'], 2); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_color; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($item['date_added'])); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($item['updated_at'])); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-info view-item" 
                                                    data-id="<?php echo $item['id']; ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-warning edit-item" 
                                                    data-id="<?php echo $item['id']; ?>"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-success adjust-quantity" 
                                                    data-id="<?php echo $item['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                    data-current="<?php echo $item['quantity']; ?>"
                                                    data-unit="<?php echo htmlspecialchars($item['unit']); ?>"
                                                    title="Adjust Quantity">
                                                <i class="fas fa-plus-minus"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger delete-item" 
                                                    data-id="<?php echo $item['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
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
    </div>
</div>

<!-- Add Food Modal -->
<div class="modal fade" id="addFoodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add New Food Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="item_name" class="form-label">Item Name *</label>
                            <input type="text" class="form-control" id="item_name" name="item_name" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="quantity" class="form-label">Quantity *</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="unit" class="form-label">Unit *</label>
                            <select class="form-select" id="unit" name="unit" required>
                                <option value="">Select Unit</option>
                                <?php foreach ($available_units as $unit): ?>
                                    <option value="<?php echo $unit; ?>"><?php echo ucfirst($unit); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_added" class="form-label">Date Added</label>
                            <input type="date" class="form-control" id="date_added" name="date_added" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="description" class="form-label">Description / Notes</label>
                            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_food" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Adjust Quantity Modal -->
<div class="modal fade" id="adjustQuantityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="" id="adjustForm">
                <input type="hidden" name="id" id="adjust_id">
                <input type="hidden" name="adjust_quantity" value="1">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-minus me-2"></i>Adjust Quantity</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-balance-scale fa-3x text-info"></i>
                        <h5 class="mt-2" id="adjustItemName"></h5>
                        <p class="text-muted">Current Quantity: <strong id="currentQuantity"></strong> <span id="currentUnit"></span></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type</label>
                        <div class="d-flex gap-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="adjust_type" id="addStock" value="add" checked>
                                <label class="form-check-label" for="addStock">Add Stock</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="adjust_type" id="removeStock" value="remove">
                                <label class="form-check-label" for="removeStock">Remove Stock</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="adjust_quantity" class="form-label">Quantity *</label>
                        <input type="number" class="form-control" id="adjust_quantity" name="quantity" step="0.01" min="0.01" required>
                        <div class="form-text">Enter the quantity to add or remove</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="adjust_reason" class="form-label">Reason / Notes</label>
                        <textarea class="form-control" id="adjust_reason" name="reason" rows="2" placeholder="e.g., New shipment, Daily consumption, etc." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-check me-2"></i>Apply Adjustment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Food Modal -->
<div class="modal fade" id="editFoodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="update_food" value="1">
                <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Food Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="editModalBody">
                    <!-- Content loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Food Item Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewDetailsContent">
                <!-- Details loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="id" id="delete_id">
                <input type="hidden" name="delete_food" value="1">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                    <h5 class="mb-3">Are you sure you want to delete this food item?</h5>
                    <p class="mb-2"><strong id="deleteItemName"></strong></p>
                    <p class="text-danger"><small>This action cannot be undone!</small></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Update Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Bulk Update</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Select items to update:</p>
                <div class="mb-3">
                    <?php foreach ($food_items as $item): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="<?php echo $item['id']; ?>" id="item_<?php echo $item['id']; ?>">
                        <label class="form-check-label" for="item_<?php echo $item['id']; ?>">
                            <?php echo htmlspecialchars($item['item_name']); ?> (<?php echo $item['quantity']; ?> <?php echo $item['unit']; ?>)
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mb-3">
                    <label for="bulkAction" class="form-label">Action</label>
                    <select class="form-select" id="bulkAction">
                        <option value="increase">Increase Quantity by %</option>
                        <option value="decrease">Decrease Quantity by %</option>
                        <option value="set">Set to Specific Value</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="bulkValue" class="form-label">Value</label>
                    <input type="number" class="form-control" id="bulkValue" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="applyBulkUpdate">
                    <i class="fas fa-check me-2"></i>Apply Update
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
// Show SweetAlert2 notifications
document.addEventListener('DOMContentLoaded', function() {
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    
    if (successMessage) {
        const message = successMessage.getAttribute('data-message');
        Swal.fire({
            title: 'Success!',
            text: message,
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '#3085d6',
            timer: 3000,
            timerProgressBar: true,
        });
    }
    
    if (errorMessage) {
        const message = errorMessage.getAttribute('data-message');
        Swal.fire({
            title: 'Error!',
            text: message,
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#d33',
        });
    }
});

// Adjust quantity
document.addEventListener('DOMContentLoaded', function() {
    const adjustButtons = document.querySelectorAll('.adjust-quantity');
    adjustButtons.forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            const itemName = this.getAttribute('data-name');
            const currentQty = this.getAttribute('data-current');
            const unit = this.getAttribute('data-unit');
            
            document.getElementById('adjust_id').value = itemId;
            document.getElementById('adjustItemName').textContent = itemName;
            document.getElementById('currentQuantity').textContent = currentQty;
            document.getElementById('currentUnit').textContent = unit;
            
            // Reset form
            document.getElementById('adjustForm').reset();
            document.getElementById('addStock').checked = true;
            
            const adjustModal = new bootstrap.Modal(document.getElementById('adjustQuantityModal'));
            adjustModal.show();
        });
    });
    
    // Handle adjust quantity form submission
    document.getElementById('adjustForm').addEventListener('submit', function(e) {
        const adjustType = document.querySelector('input[name="adjust_type"]:checked').value;
        const quantity = parseFloat(document.getElementById('adjust_quantity').value);
        const currentQty = parseFloat(document.getElementById('currentQuantity').textContent);
        
        if (adjustType === 'remove') {
            if (quantity > currentQty) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error!',
                    text: 'Cannot remove more than available quantity!',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return false;
            }
        }
        
        if (quantity <= 0) {
            e.preventDefault();
            Swal.fire({
                title: 'Error!',
                text: 'Please enter a valid quantity greater than 0!',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        return true;
    });
});

// Edit item functionality
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.edit-item');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            
            // Show loading
            document.getElementById('editModalBody').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading item details...</p>
                </div>
            `;
            
            fetch('get_food_item.php?id=' + itemId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    document.getElementById('editModalBody').innerHTML = data;
                    document.getElementById('edit_id').value = itemId;
                    
                    const editModal = new bootstrap.Modal(document.getElementById('editFoodModal'));
                    editModal.show();
                })
                .catch(error => {
                    document.getElementById('editModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading item details. Please try again.
                        </div>
                    `;
                    console.error('Error:', error);
                });
        });
    });
    
    // View item details
    const viewButtons = document.querySelectorAll('.view-item');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            
            // Show loading
            document.getElementById('viewDetailsContent').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading item details...</p>
                </div>
            `;
            
            fetch('get_food_details.php?id=' + itemId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    document.getElementById('viewDetailsContent').innerHTML = data;
                    const viewModal = new bootstrap.Modal(document.getElementById('viewDetailsModal'));
                    viewModal.show();
                })
                .catch(error => {
                    document.getElementById('viewDetailsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading item details. Please try again.
                        </div>
                    `;
                    console.error('Error:', error);
                });
        });
    });
    
    // Delete confirmation
    const deleteButtons = document.querySelectorAll('.delete-item');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            const itemName = this.getAttribute('data-name');
            
            document.getElementById('delete_id').value = itemId;
            document.getElementById('deleteItemName').textContent = itemName;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
            deleteModal.show();
        });
    });
});

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const rows = document.querySelectorAll('#foodTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    });
});

// Filter functionality
function filterTable() {
    const status = document.getElementById('statusFilter').value;
    const unit = document.getElementById('unitFilter').value;
    const rows = document.querySelectorAll('#foodTable tbody tr');
    
    rows.forEach(row => {
        if (row.style.display === 'none' || row.cells.length < 8) return;
        
        const rowStatus = row.cells[4].textContent.trim().toLowerCase().replace(' ', '_');
        const rowUnit = row.cells[3].textContent.trim().toLowerCase();
        
        const showStatus = !status || rowStatus === status.toLowerCase();
        const showUnit = !unit || rowUnit === unit.toLowerCase();
        
        row.style.display = (showStatus && showUnit) ? '' : 'none';
    });
}

document.getElementById('statusFilter').addEventListener('change', filterTable);
document.getElementById('unitFilter').addEventListener('change', filterTable);

// Bulk update
document.getElementById('applyBulkUpdate').addEventListener('click', function() {
    const selectedItems = [];
    document.querySelectorAll('#bulkUpdateModal .form-check-input:checked').forEach(checkbox => {
        selectedItems.push(checkbox.value);
    });
    
    if (selectedItems.length === 0) {
        Swal.fire('Error', 'Please select at least one item!', 'error');
        return;
    }
    
    const action = document.getElementById('bulkAction').value;
    const value = parseFloat(document.getElementById('bulkValue').value);
    
    if (isNaN(value) || value <= 0) {
        Swal.fire('Error', 'Please enter a valid positive number!', 'error');
        return;
    }
    
    // Here you would send an AJAX request to process bulk update
    // For now, just show a message
    Swal.fire('Info', 'Bulk update would process ' + selectedItems.length + ' items with ' + action + ' action.', 'info');
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('bulkUpdateModal'));
    modal.hide();
});
</script>

<style>
/* Custom stats card styles - Compact Version */
.card-header{
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);
}

/* Main Three Compact Stats Cards */
.compact-stats-row {
    margin-bottom: 1rem;
}

.compact-stats-card {
    background: white;
    border-radius: 12px;
    padding: 12px 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
    height: 80px;
}

.compact-stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}

.compact-stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.compact-stats-icon.bg-primary {
    background: linear-gradient(135deg, #4e73df, #3a56c4);
}

.compact-stats-icon.bg-warning {
    background: linear-gradient(135deg, #f6c23e, #f4b619);
}

.compact-stats-icon.bg-danger {
    background: linear-gradient(135deg, #e74a3b, #d52a1b);
}

.compact-stats-content {
    flex: 1;
}

.compact-stats-number {
    font-size: 1.6rem;
    font-weight: bold;
    color: #2c3e50;
    line-height: 1.2;
    margin-bottom: 2px;
}

.compact-stats-label {
    font-size: 0.75rem;
    color: #7b8a8b;
    font-weight: 500;
}

.compact-stats-subtext {
    font-size: 0.65rem;
    color: #95a5a6;
}

/* Secondary Mini Stats Cards */
.secondary-stats-row {
    margin-bottom: 1.5rem;
}

.mini-stats-card {
    background: white;
    border-radius: 10px;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
    height: 65px;
}

.mini-stats-card:hover {
    background: #f8f9fa;
    transform: translateY(-1px);
}

.mini-stats-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(59, 157, 179, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.mini-stats-info {
    flex: 1;
}

.mini-stats-number {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    line-height: 1.2;
}

.mini-stats-label {
    font-size: 0.7rem;
    color: #95a5a6;
    font-weight: 500;
}

/* Table styles */
.table th {
    background-color: rgba(59, 157, 179, 0.05);
    font-weight: 600;
}

.btn-group .btn {
    border-radius: 4px;
    margin: 1px;
}

.modal-header {
    border-radius: 10px 10px 0 0;
}

.form-control, .form-select {
    border-radius: 8px;
    padding: 10px;
}

.form-control:focus, .form-select:focus {
    border-color: #3B9DB3;
    box-shadow: 0 0 0 0.2rem rgba(59, 157, 179, 0.25);
}

@keyframes slideIn {
    from {
        transform: translate(-50%, -40px);
        opacity: 0;
    }
    to {
        transform: translate(-50%, -50%);
        opacity: 1;
    }
}

@media (max-width: 768px) {
    .compact-stats-card {
        padding: 10px 12px;
        height: 70px;
    }
    
    .compact-stats-icon {
        width: 42px;
        height: 42px;
        font-size: 1.2rem;
    }
    
    .compact-stats-number {
        font-size: 1.3rem;
    }
    
    .compact-stats-label {
        font-size: 0.7rem;
    }
    
    .mini-stats-card {
        padding: 8px 10px;
        height: 60px;
    }
    
    .mini-stats-icon {
        width: 32px;
        height: 32px;
        font-size: 0.9rem;
    }
    
    .mini-stats-number {
        font-size: 0.95rem;
    }
    
    .btn-group {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-group .btn {
        width: 100%;
        margin-bottom: 5px;
        border-radius: 4px;
    }
    
    .modal-dialog {
        margin: 10px;
    }
}

.item-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #3B9DB3, #2d7c8f);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.item-icon i {
    color: white;
}
</style>

<?php include '../controller/footer.php'; ?>