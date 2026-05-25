<?php
// sports_store.php - Sports Equipment Store Management
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Check user roles for permissions
$user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}

// Check if user has Sports & Game role (role_id 6) or admin roles
$has_sports_role = false;
$is_admin_role = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2) {
        $is_admin_role = true;
        $has_sports_role = true;
    }
    if ($role_id == 6) {
        $has_sports_role = true;
    }
}

$can_manage = $is_admin_role || $has_sports_role;
$can_delete = $is_admin_role;

if (!$can_manage) {
    $_SESSION['error'] = "You don't have permission to manage sports equipment.";
    header("Location: ../404.php");
    exit();
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
$status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// Handle Add/Edit/Delete operations
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // Add new equipment
    if ($_POST['action'] == 'add_equipment' && $can_manage) {
        $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
        $category = mysqli_real_escape_string($conn, $_POST['category']);
        $unit = mysqli_real_escape_string($conn, $_POST['unit']);
        $quantity = intval($_POST['quantity']);
        $min_quantity = intval($_POST['min_quantity']);
        $short_note = mysqli_real_escape_string($conn, $_POST['short_note']);
        $purchase_date = date('Y-m-d'); // Default to current date
        $purchase_price = floatval($_POST['purchase_price']);
        
        // Handle image upload
        $image_path = null;
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['item_image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $upload_dir = '../uploads/sports_equipment/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_path)) {
                    $image_path = 'uploads/sports_equipment/' . $filename;
                }
            }
        }
        
        $insert_sql = "INSERT INTO sports_equipment (item_name, category, unit, quantity, min_quantity, 
                        short_note, image_path, purchase_date, purchase_price, created_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sssiisssdi", $item_name, $category, $unit, $quantity, $min_quantity, 
                          $short_note, $image_path, $purchase_date, $purchase_price, $admin_id);
        
        if ($stmt->execute()) {
            $equipment_id = $stmt->insert_id;
            
            // Record initial stock in transaction if quantity > 0
            if ($quantity > 0) {
                $transaction_sql = "INSERT INTO equipment_transactions (equipment_id, transaction_type, quantity, 
                                    previous_quantity, new_quantity, reason, performed_by, created_at) 
                                    VALUES (?, 'IN', ?, 0, ?, 'Initial stock addition', ?, NOW())";
                $stmt2 = $conn->prepare($transaction_sql);
                $stmt2->bind_param("iiii", $equipment_id, $quantity, $quantity, $admin_id);
                $stmt2->execute();
            }
            
            $_SESSION['success'] = "Equipment added successfully!";
        } else {
            $_SESSION['error'] = "Error adding equipment: " . $conn->error;
        }
        header("Location: sports_store.php?category=" . urlencode($category) . "&search=" . urlencode($search));
        exit();
    }
    
    // Edit equipment
    if ($_POST['action'] == 'edit_equipment' && $can_manage) {
        $equipment_id = intval($_POST['equipment_id']);
        $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
        $category = mysqli_real_escape_string($conn, $_POST['category']);
        $unit = mysqli_real_escape_string($conn, $_POST['unit']);
        $quantity = intval($_POST['quantity']);
        $min_quantity = intval($_POST['min_quantity']);
        $short_note = mysqli_real_escape_string($conn, $_POST['short_note']);
        $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : date('Y-m-d');
        $purchase_price = floatval($_POST['purchase_price']);
        
        // Get current quantity before update
        $current_sql = "SELECT quantity FROM sports_equipment WHERE id = ?";
        $stmt = $conn->prepare($current_sql);
        $stmt->bind_param("i", $equipment_id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $old_quantity = $current['quantity'];
        
        // Get current image path
        $current_image_sql = "SELECT image_path FROM sports_equipment WHERE id = ?";
        $stmt = $conn->prepare($current_image_sql);
        $stmt->bind_param("i", $equipment_id);
        $stmt->execute();
        $current_image = $stmt->get_result()->fetch_assoc();
        $image_path = $current_image['image_path'] ?? null;
        
        // Handle new image upload
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['item_image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $upload_dir = '../uploads/sports_equipment/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Delete old image if exists
                if ($image_path && file_exists('../' . $image_path)) {
                    unlink('../' . $image_path);
                }
                
                $file_extension = pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_path)) {
                    $image_path = 'uploads/sports_equipment/' . $filename;
                }
            }
        }
        
        // Handle remove image
        if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            if ($image_path && file_exists('../' . $image_path)) {
                unlink('../' . $image_path);
            }
            $image_path = null;
        }
        
        $update_sql = "UPDATE sports_equipment SET item_name = ?, category = ?, unit = ?, quantity = ?, 
                       min_quantity = ?, short_note = ?, image_path = ?, purchase_date = ?, purchase_price = ?,
                       updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssiisssdi", $item_name, $category, $unit, $quantity, $min_quantity, 
                          $short_note, $image_path, $purchase_date, $purchase_price, $equipment_id);
        
        if ($stmt->execute()) {
            // Record quantity change if different
            if ($quantity != $old_quantity) {
                $quantity_diff = $quantity - $old_quantity;
                $transaction_type = $quantity_diff > 0 ? 'IN' : 'OUT';
                $reason = "Stock adjusted during equipment update";
                
                $transaction_sql = "INSERT INTO equipment_transactions (equipment_id, transaction_type, quantity, 
                                    previous_quantity, new_quantity, reason, performed_by, created_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt2 = $conn->prepare($transaction_sql);
                $stmt2->bind_param("isiiisi", $equipment_id, $transaction_type, abs($quantity_diff), 
                                   $old_quantity, $quantity, $reason, $admin_id);
                $stmt2->execute();
            }
            
            $_SESSION['success'] = "Equipment updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating equipment: " . $conn->error;
        }
        header("Location: sports_store.php?category=" . urlencode($category) . "&search=" . urlencode($search));
        exit();
    }
    
    // Delete equipment
    if ($_POST['action'] == 'delete_equipment' && $can_delete) {
        $equipment_id = intval($_POST['equipment_id']);
        
        // Check if equipment has been used in any transactions
        $check_usage_sql = "SELECT COUNT(*) as usage_count FROM equipment_transactions WHERE equipment_id = ?";
        $stmt = $conn->prepare($check_usage_sql);
        $stmt->bind_param("i", $equipment_id);
        $stmt->execute();
        $usage = $stmt->get_result()->fetch_assoc();
        
        if ($usage['usage_count'] > 0) {
            // Soft delete - mark as archived instead of permanent delete
            $archive_sql = "UPDATE sports_equipment SET is_archived = TRUE, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($archive_sql);
            $stmt->bind_param("i", $equipment_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Equipment has been archived (has transaction history).";
            } else {
                $_SESSION['error'] = "Error archiving equipment.";
            }
        } else {
            // Get image path to delete
            $image_sql = "SELECT image_path FROM sports_equipment WHERE id = ?";
            $stmt = $conn->prepare($image_sql);
            $stmt->bind_param("i", $equipment_id);
            $stmt->execute();
            $equipment = $stmt->get_result()->fetch_assoc();
            
            if ($equipment['image_path'] && file_exists('../' . $equipment['image_path'])) {
                unlink('../' . $equipment['image_path']);
            }
            
            $delete_sql = "DELETE FROM sports_equipment WHERE id = ?";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param("i", $equipment_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Equipment deleted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting equipment: " . $conn->error;
            }
        }
        header("Location: sports_store.php?category=" . urlencode($category) . "&search=" . urlencode($search));
        exit();
    }
    
    // Update stock (add or remove)
    if ($_POST['action'] == 'update_stock' && $can_manage) {
        $equipment_id = intval($_POST['equipment_id']);
        $quantity_change = intval($_POST['quantity_change']);
        $direction = $_POST['direction'];
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        
        // Adjust quantity based on direction
        if ($direction == 'remove') {
            $quantity_change = -abs($quantity_change);
        } else {
            $quantity_change = abs($quantity_change);
        }
        
        $transaction_type = $quantity_change > 0 ? 'IN' : 'OUT';
        
        // Get current quantity
        $current_sql = "SELECT item_name, quantity FROM sports_equipment WHERE id = ?";
        $stmt = $conn->prepare($current_sql);
        $stmt->bind_param("i", $equipment_id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        
        $new_quantity = $current['quantity'] + $quantity_change;
        
        if ($new_quantity < 0) {
            $_SESSION['error'] = "Cannot remove more than available stock! Current stock: " . $current['quantity'];
            header("Location: sports_store.php?category=" . urlencode($category) . "&search=" . urlencode($search));
            exit();
        }
        
        // Update quantity
        $update_sql = "UPDATE sports_equipment SET quantity = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ii", $new_quantity, $equipment_id);
        
        if ($stmt->execute()) {
            // Record transaction
            $transaction_sql = "INSERT INTO equipment_transactions (equipment_id, transaction_type, quantity, 
                                previous_quantity, new_quantity, reason, performed_by, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt2 = $conn->prepare($transaction_sql);
            $stmt2->bind_param("isiiisi", $equipment_id, $transaction_type, abs($quantity_change), 
                               $current['quantity'], $new_quantity, $reason, $admin_id);
            $stmt2->execute();
            
            // Set success message based on action
            if ($quantity_change > 0) {
                $_SESSION['success'] = "Added " . abs($quantity_change) . " " . $current['item_name'] . " to stock!";
            } else {
                $_SESSION['success'] = "Removed " . abs($quantity_change) . " " . $current['item_name'] . " from stock!";
            }
        } else {
            $_SESSION['error'] = "Error updating stock: " . $conn->error;
        }
        header("Location: sports_store.php?category=" . urlencode($category) . "&search=" . urlencode($search));
        exit();
    }
}

// Get all equipment with filters
$where_conditions = ["is_archived = FALSE"];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(item_name LIKE ? OR short_note LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ss";
}

if (!empty($category)) {
    $where_conditions[] = "category = ?";
    $params[] = $category;
    $param_types .= "s";
}

if (!empty($status)) {
    if ($status == 'low') {
        $where_conditions[] = "quantity <= min_quantity AND quantity > 0";
    } elseif ($status == 'out') {
        $where_conditions[] = "quantity = 0";
    } elseif ($status == 'in') {
        $where_conditions[] = "quantity > 0";
    }
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

$equipment_sql = "SELECT * FROM sports_equipment $where_clause ORDER BY 
                  CASE WHEN quantity <= min_quantity THEN 0 ELSE 1 END, 
                  item_name ASC";

$stmt = $conn->prepare($equipment_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$equipment_result = $stmt->get_result();
$equipment_data = mysqli_fetch_all($equipment_result, MYSQLI_ASSOC);

// Get unique categories for filter
$categories_sql = "SELECT DISTINCT category FROM sports_equipment WHERE is_archived = FALSE ORDER BY category";
$categories_result = mysqli_query($conn, $categories_sql);

// Get summary statistics
$stats_sql = "SELECT 
    COUNT(*) as total_items,
    SUM(quantity) as total_quantity,
    SUM(CASE WHEN quantity <= min_quantity AND quantity > 0 THEN 1 ELSE 0 END) as low_stock_items,
    SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock
    FROM sports_equipment WHERE is_archived = FALSE";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get recent transactions for activity feed
$recent_transactions_sql = "SELECT t.*, e.item_name, e.unit,
                            CONCAT(a.first_name, ' ', a.last_name) as performed_by_name
                            FROM equipment_transactions t
                            LEFT JOIN sports_equipment e ON t.equipment_id = e.id
                            LEFT JOIN admins a ON t.performed_by = a.id
                            ORDER BY t.created_at DESC LIMIT 5";
$recent_transactions = mysqli_query($conn, $recent_transactions_sql);

// Get equipment for view modal
$view_equipment = null;
if (isset($_GET['view_id'])) {
    $view_id = intval($_GET['view_id']);
    $view_sql = "SELECT * FROM sports_equipment WHERE id = ?";
    $stmt = $conn->prepare($view_sql);
    $stmt->bind_param("i", $view_id);
    $stmt->execute();
    $view_result = $stmt->get_result();
    $view_equipment = $view_result->fetch_assoc();
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="page-title">
                    <i class="fas fa-boxes me-2"></i>
                    Sports Equipment Store
                </h2>
                <p class="text-muted">Manage sports equipment inventory, track stock levels, and record transactions</p>
            </div>
            <div>
                <a href="sports.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-futbol me-2"></i>Back to Sports
                </a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                    <i class="fas fa-plus me-2"></i>Add Equipment
                </button>
                <a href="sports_store_transactions.php" class="btn btn-info">
                    <i class="fas fa-history me-2"></i>Transaction History
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-box" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo $stats['total_items'] ?? 0; ?></h3>
                    <p>Total Equipment Types</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-cubes" style="color: #28a745;"></i></div>
                    <h3><?php echo $stats['total_quantity'] ?? 0; ?></h3>
                    <p>Total Items in Stock</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i></div>
                    <h3><?php echo $stats['low_stock_items'] ?? 0; ?></h3>
                    <p>Low Stock Items</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-ban" style="color: #dc3545;"></i></div>
                    <h3><?php echo $stats['out_of_stock'] ?? 0; ?></h3>
                    <p>Out of Stock</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Equipment List - Main Content -->
            <div class="col-lg-8">
                <!-- Filter Bar -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" placeholder="Search by item name or note..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="">All Categories</option>
                                    <?php 
                                    mysqli_data_seek($categories_result, 0);
                                    while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Stock Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="in" <?php echo $status == 'in' ? 'selected' : ''; ?>>In Stock</option>
                                    <option value="low" <?php echo $status == 'low' ? 'selected' : ''; ?>>Low Stock</option>
                                    <option value="out" <?php echo $status == 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-2"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Equipment List -->
                <div class="card">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Equipment Inventory</h5>
                            <div>
                                <span class="badge bg-light text-dark"><?php echo count($equipment_data); ?> items</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($equipment_data) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped table-bordered">
                                    <thead class="table-light">
                                        <tr style="text-align: center; vertical-align: middle;">
                                            <th style="width: 60px;">Image</th>
                                            <th>Item Name</th>
                                            <th>Category</th>
                                            <th style="width: 80px;">Unit</th>
                                            <th style="width: 100px;">Quantity</th>
                                            <th style="width: 80px;">Min Stock</th>
                                            <th style="width: 100px;">Status</th>
                                            <th style="width: 80px;">Notes</th>
                                            <th style="width: 150px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($equipment_data as $item): 
                                            $stock_status = '';
                                            $status_class = '';
                                            $status_icon = '';
                                            if ($item['quantity'] <= 0) {
                                                $stock_status = 'Out of Stock';
                                                $status_class = 'danger';
                                                $status_icon = 'fa-times-circle';
                                            } elseif ($item['quantity'] <= $item['min_quantity']) {
                                                $stock_status = 'Low Stock';
                                                $status_class = 'warning';
                                                $status_icon = 'fa-exclamation-triangle';
                                            } else {
                                                $stock_status = 'In Stock';
                                                $status_class = 'success';
                                                $status_icon = 'fa-check-circle';
                                            }
                                        ?>
                                            <tr style="text-align: center; vertical-align: middle;">
                                                <td>
                                                    <?php if ($item['image_path'] && file_exists('../' . $item['image_path'])): ?>
                                                        <img src="../<?php echo $item['image_path']; ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" style="width: 45px; height: 45px; object-fit: cover; border-radius: 8px;">
                                                    <?php else: ?>
                                                        <div style="width: 45px; height: 45px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                                            <i class="fas fa-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($item['category']); ?></span></td>
                                                <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $status_class; ?> fs-6" style="font-size: 1rem; padding: 8px 12px;">
                                                        <?php echo $item['quantity']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $item['min_quantity']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $status_class; ?>">
                                                        <i class="fas <?php echo $status_icon; ?> me-1"></i><?php echo $stock_status; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($item['short_note'])): ?>
                                                        <span title="<?php echo htmlspecialchars($item['short_note']); ?>">
                                                            <?php echo strlen($item['short_note']) > 20 ? substr(htmlspecialchars($item['short_note']), 0, 20) . '...' : htmlspecialchars($item['short_note']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="?view_id=<?php echo $item['id']; ?>" class="btn btn-outline-info view-equipment" data-bs-toggle="modal" data-bs-target="#viewEquipmentModal" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button class="btn btn-outline-primary update-stock" 
                                                                data-id="<?php echo $item['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                                data-current-qty="<?php echo $item['quantity']; ?>"
                                                                title="Update Stock">
                                                            <i class="fas fa-exchange-alt"></i>
                                                        </button>
                                                        <button class="btn btn-outline-warning edit-equipment" 
                                                                data-id="<?php echo $item['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                                data-category="<?php echo htmlspecialchars($item['category']); ?>"
                                                                data-unit="<?php echo htmlspecialchars($item['unit']); ?>"
                                                                data-quantity="<?php echo $item['quantity']; ?>"
                                                                data-min-quantity="<?php echo $item['min_quantity']; ?>"
                                                                data-note="<?php echo htmlspecialchars($item['short_note']); ?>"
                                                                data-purchase-date="<?php echo $item['purchase_date']; ?>"
                                                                data-purchase-price="<?php echo $item['purchase_price']; ?>"
                                                                data-image="<?php echo $item['image_path']; ?>"
                                                                title="Edit Equipment">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($can_delete): ?>
                                                            <button class="btn btn-outline-danger delete-equipment" 
                                                                    data-id="<?php echo $item['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                                    title="Delete Equipment">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <h5>No equipment found</h5>
                                <p class="text-muted">Click "Add Equipment" to start managing your sports inventory.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Sidebar -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($recent_transactions && mysqli_num_rows($recent_transactions) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($activity = mysqli_fetch_assoc($recent_transactions)): 
                                    $icon = $activity['transaction_type'] == 'IN' ? 'fa-arrow-down text-success' : 'fa-arrow-up text-danger';
                                    $action_text = $activity['transaction_type'] == 'IN' ? 'Added' : 'Removed';
                                ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <i class="fas <?php echo $icon; ?> me-2"></i>
                                                <strong><?php echo htmlspecialchars($activity['item_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo $action_text; ?> <?php echo $activity['quantity']; ?> <?php echo $activity['unit']; ?>
                                                    (<?php echo $activity['previous_quantity']; ?> → <?php echo $activity['new_quantity']; ?>)
                                                </small>
                                            </div>
                                            <small class="text-muted"><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></small>
                                        </div>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($activity['performed_by_name'] ?? 'System'); ?>
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-comment me-1"></i><?php echo htmlspecialchars(substr($activity['reason'], 0, 50)); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            <div class="card-footer text-center">
                                <a href="sports_store_transactions.php" class="btn btn-sm btn-outline-primary">
                                    View All Transactions <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-history fa-2x text-muted mb-2"></i>
                                <p class="text-muted">No recent activity</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Stats Card -->
                <div class="card mt-4">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                        <h5 class="mb-0"><i class="fas fa-chart-simple me-2"></i>Quick Stats</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get category distribution
                        $category_stats = "SELECT category, COUNT(*) as count, SUM(quantity) as total_qty 
                                          FROM sports_equipment WHERE is_archived = FALSE 
                                          GROUP BY category ORDER BY total_qty DESC LIMIT 5";
                        $category_result = mysqli_query($conn, $category_stats);
                        ?>
                        <h6>Top Categories by Stock</h6>
                        <?php while ($cat = mysqli_fetch_assoc($category_result)): ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span><?php echo htmlspecialchars($cat['category']); ?></span>
                                    <span><?php echo $cat['total_qty']; ?> items</span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <?php 
                                    $max_qty = 100;
                                    $width = min(($cat['total_qty'] / $max_qty) * 100, 100);
                                    ?>
                                    <div class="progress-bar bg-primary" style="width: <?php echo $width; ?>%"></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        
                        <hr>
                        
                        <h6>Stock Alerts</h6>
                        <?php
                        $alert_items = "SELECT item_name, quantity, min_quantity FROM sports_equipment 
                                       WHERE quantity <= min_quantity AND is_archived = FALSE 
                                       ORDER BY quantity ASC LIMIT 5";
                        $alert_result = mysqli_query($conn, $alert_items);
                        if (mysqli_num_rows($alert_result) > 0):
                            while ($alert = mysqli_fetch_assoc($alert_result)):
                        ?>
                            <div class="alert alert-warning py-2 mb-2">
                                <i class="fas fa-exclamation-circle me-1"></i>
                                <strong><?php echo htmlspecialchars($alert['item_name']); ?></strong><br>
                                <small>Current: <?php echo $alert['quantity']; ?> | Min: <?php echo $alert['min_quantity']; ?></small>
                            </div>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <p class="text-success"><i class="fas fa-check-circle me-1"></i> All items are well stocked!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Equipment Modal -->
<div class="modal fade" id="viewEquipmentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Equipment Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($view_equipment): ?>
                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <?php if ($view_equipment['image_path'] && file_exists('../' . $view_equipment['image_path'])): ?>
                                <img src="../<?php echo $view_equipment['image_path']; ?>" alt="<?php echo htmlspecialchars($view_equipment['item_name']); ?>" class="img-fluid rounded" style="max-height: 200px;">
                            <?php else: ?>
                                <div style="width: 100%; height: 150px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <h4><?php echo htmlspecialchars($view_equipment['item_name']); ?></h4>
                            <hr>
                            <div class="row">
                                <div class="col-sm-6">
                                    <p><strong><i class="fas fa-tag me-1"></i> Category:</strong> <?php echo htmlspecialchars($view_equipment['category']); ?></p>
                                    <p><strong><i class="fas fa-balance-scale me-1"></i> Unit:</strong> <?php echo htmlspecialchars($view_equipment['unit']); ?></p>
                                    <p><strong><i class="fas fa-cubes me-1"></i> Current Quantity:</strong> 
                                        <span class="badge bg-<?php echo $view_equipment['quantity'] <= 0 ? 'danger' : ($view_equipment['quantity'] <= $view_equipment['min_quantity'] ? 'warning' : 'success'); ?>">
                                            <?php echo $view_equipment['quantity']; ?>
                                        </span>
                                    </p>
                                    <p><strong><i class="fas fa-exclamation-triangle me-1"></i> Min Stock Level:</strong> <?php echo $view_equipment['min_quantity']; ?></p>
                                </div>
                                <div class="col-sm-6">
                                    <p><strong><i class="fas fa-calendar-alt me-1"></i> Purchase Date:</strong> <?php echo $view_equipment['purchase_date'] ? date('M d, Y', strtotime($view_equipment['purchase_date'])) : date('M d, Y'); ?></p>
                                    <p><strong><i class="fas fa-money-bill me-1"></i> Purchase Price:</strong> <?php echo $view_equipment['purchase_price'] ? 'TZS ' . number_format($view_equipment['purchase_price'], 2) : 'TZS 0.00'; ?></p>
                                </div>
                            </div>
                            <?php if (!empty($view_equipment['short_note'])): ?>
                                <div class="mt-2">
                                    <strong><i class="fas fa-sticky-note me-1"></i> Notes:</strong>
                                    <p class="text-muted mt-1"><?php echo nl2br(htmlspecialchars($view_equipment['short_note'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-center">Select an equipment to view details.</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Equipment Modal -->
<div class="modal fade" id="addEquipmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Equipment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_equipment">
                    
                    <div class="mb-3">
                        <label class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" class="form-control" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <option value="">Select Category</option>
                                <option value="Football">Football</option>
                                <option value="Netball">Netball</option>
                                <option value="Handball">Handball</option>
                                <option value="Volleyball">Volleyball</option>
                                <option value="Athletics">Athletics</option>
                                <option value="Training">Training Equipment</option>
                                <option value="Uniforms">Uniforms</option>
                                <option value="Medical">Medical/First Aid</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit <span class="text-danger">*</span></label>
                            <select name="unit" class="form-select" required>
                                <option value="">Select Unit</option>
                                <option value="pcs">Pieces (pcs)</option>
                                <option value="set">Set</option>
                                <option value="pair">Pair</option>
                                <option value="ball">Ball</option>
                                <option value="kit">Kit</option>
                                <option value="litre">Litre</option>
                                <option value="kg">Kilogram (kg)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Initial Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control" min="0" value="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Minimum Stock Level <span class="text-danger">*</span></label>
                            <input type="number" name="min_quantity" class="form-control" min="0" value="5" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Purchase Price (TZS)</label>
                            <input type="number" name="purchase_price" class="form-control" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Item Image</label>
                            <input type="file" name="item_image" class="form-control" accept="image/*">
                            <small class="text-muted">JPG, PNG, GIF, WEBP</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Short Note</label>
                        <textarea name="short_note" class="form-control" rows="3" placeholder="e.g., Brand, size, color, condition..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Equipment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Equipment Modal -->
<div class="modal fade" id="editEquipmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Equipment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_equipment">
                    <input type="hidden" name="equipment_id" id="edit_equipment_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" id="edit_item_name" class="form-control" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" id="edit_category" class="form-select" required>
                                <option value="">Select Category</option>
                                <option value="Football">Football</option>
                                <option value="Netball">Netball</option>
                                <option value="Handball">Handball</option>
                                <option value="Volleyball">Volleyball</option>
                                <option value="Athletics">Athletics</option>
                                <option value="Training">Training Equipment</option>
                                <option value="Uniforms">Uniforms</option>
                                <option value="Medical">Medical/First Aid</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit <span class="text-danger">*</span></label>
                            <select name="unit" id="edit_unit" class="form-select" required>
                                <option value="">Select Unit</option>
                                <option value="pcs">Pieces (pcs)</option>
                                <option value="set">Set</option>
                                <option value="pair">Pair</option>
                                <option value="ball">Ball</option>
                                <option value="kit">Kit</option>
                                <option value="litre">Litre</option>
                                <option value="kg">Kilogram (kg)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" id="edit_quantity" class="form-control" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Minimum Stock Level <span class="text-danger">*</span></label>
                            <input type="number" name="min_quantity" id="edit_min_quantity" class="form-control" min="0" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Purchase Date</label>
                            <input type="date" name="purchase_date" id="edit_purchase_date" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Purchase Price (TZS)</label>
                            <input type="number" name="purchase_price" id="edit_purchase_price" class="form-control" min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Short Note</label>
                        <textarea name="short_note" id="edit_short_note" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Image</label>
                        <div id="current_image_preview" class="mb-2"></div>
                        <input type="file" name="item_image" class="form-control" accept="image/*">
                        <small class="text-muted">Leave empty to keep current image. Upload new to replace.</small>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_image" id="remove_image" value="1">
                            <label class="form-check-label text-danger" for="remove_image">
                                Remove current image
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Equipment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Stock Modal -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-exchange-alt me-2"></i>Update Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_stock">
                    <input type="hidden" name="equipment_id" id="stock_equipment_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Equipment</label>
                        <input type="text" id="stock_equipment_name" class="form-control" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Quantity</label>
                        <input type="number" id="stock_current_qty" class="form-control" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Action <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select name="direction" id="stock_direction" class="form-select" style="max-width: 120px;" required>
                                <option value="add">+ Add Stock</option>
                                <option value="remove">- Remove Stock</option>
                            </select>
                            <input type="number" name="quantity_change" id="stock_quantity" class="form-control" min="1" required placeholder="Quantity">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason / Notes <span class="text-danger">*</span></label>
                        <textarea name="reason" id="stock_reason" class="form-control" rows="3" required 
                                  placeholder="e.g., New purchase, Issued to football team, Damaged, Returned, etc."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> All stock movements will be recorded in transaction history with timestamp and your name.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
// View Equipment Modal
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const viewId = urlParams.get('view_id');
    if (viewId) {
        const newUrl = window.location.pathname + window.location.search.replace(/[?&]view_id=[^&]*/, '').replace(/^&/, '?');
        window.history.replaceState({}, document.title, newUrl);
    }
});

document.querySelectorAll('.view-equipment').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        const viewUrl = this.getAttribute('href');
        window.location.href = viewUrl;
    });
});

// Edit Equipment Modal
const editButtons = document.querySelectorAll('.edit-equipment');
editButtons.forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('edit_equipment_id').value = this.getAttribute('data-id');
        document.getElementById('edit_item_name').value = this.getAttribute('data-name');
        document.getElementById('edit_category').value = this.getAttribute('data-category');
        document.getElementById('edit_unit').value = this.getAttribute('data-unit');
        document.getElementById('edit_quantity').value = this.getAttribute('data-quantity');
        document.getElementById('edit_min_quantity').value = this.getAttribute('data-min-quantity');
        document.getElementById('edit_short_note').value = this.getAttribute('data-note') || '';
        document.getElementById('edit_purchase_date').value = this.getAttribute('data-purchase-date');
        document.getElementById('edit_purchase_price').value = this.getAttribute('data-purchase-price') || 0;
        
        const imagePath = this.getAttribute('data-image');
        const previewDiv = document.getElementById('current_image_preview');
        if (imagePath && imagePath !== '') {
            previewDiv.innerHTML = `<img src="../${imagePath}" style="max-width: 100px; max-height: 100px; border-radius: 8px;">`;
        } else {
            previewDiv.innerHTML = '<span class="text-muted">No image</span>';
        }
        
        document.getElementById('remove_image').checked = false;
        
        const modal = new bootstrap.Modal(document.getElementById('editEquipmentModal'));
        modal.show();
    });
});

// Update Stock Modal
const stockButtons = document.querySelectorAll('.update-stock');
stockButtons.forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('stock_equipment_id').value = this.getAttribute('data-id');
        document.getElementById('stock_equipment_name').value = this.getAttribute('data-name');
        document.getElementById('stock_current_qty').value = this.getAttribute('data-current-qty');
        document.getElementById('stock_quantity').value = '';
        document.getElementById('stock_reason').value = '';
        document.getElementById('stock_direction').value = 'add';
        
        const modal = new bootstrap.Modal(document.getElementById('updateStockModal'));
        modal.show();
    });
});

// Delete Equipment Confirmation
const deleteButtons = document.querySelectorAll('.delete-equipment');
deleteButtons.forEach(button => {
    button.addEventListener('click', function() {
        const equipmentId = this.getAttribute('data-id');
        const equipmentName = this.getAttribute('data-name');
        
        Swal.fire({
            title: 'Delete Equipment?',
            html: `Are you sure you want to delete "<strong>${equipmentName}</strong>"?<br><br>
                   <span class="text-danger">This action cannot be undone!</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_equipment">
                    <input type="hidden" name="equipment_id" value="${equipmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});

// SweetAlert notifications
<?php if (isset($_SESSION['success'])): ?>
    Swal.fire({
        title: 'Success!',
        text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
        icon: 'success',
        confirmButtonText: 'OK',
        confirmButtonColor: '#3085d6',
        timer: 3000,
        timerProgressBar: true
    });
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    Swal.fire({
        title: 'Error!',
        text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
        icon: 'error',
        confirmButtonText: 'OK',
        confirmButtonColor: '#d33'
    });
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
</script>

<style>
/* Sports Store Styles */
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

.stats-card.simple-card .stats-icon i {
    font-size: 2.2rem;
    margin-bottom: 10px;
}

.stats-card.simple-card h3 {
    font-size: 1.8rem;
    font-weight: bold;
    margin: 10px 0;
}

.table th {
    font-weight: 600;
    background-color: rgba(59, 157, 179, 0.05);
    border-bottom: 2px solid rgba(59, 157, 179, 0.2);
    padding: 12px 8px;
}

.badge {
    font-weight: 500;
    padding: 5px 10px;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    margin: 0 2px;
}

.table tbody tr:hover {
    background-color: rgba(59, 157, 179, 0.05);
}

.list-group-item {
    transition: all 0.2s ease;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}

.progress {
    background-color: #e9ecef;
    border-radius: 10px;
}

@media (max-width: 768px) {
    .stats-card.simple-card h3 {
        font-size: 1.5rem;
    }
    
    .table-responsive {
        font-size: 0.85rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.2rem 0.4rem;
        margin: 2px;
    }
}
</style>

<?php include '../controller/footer.php'; ?>