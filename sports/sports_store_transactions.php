<?php
// sports_store_transactions.php - Equipment Transaction History with Edit/Delete and Batch Delete
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

$has_access = false;
$is_admin_role = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2) {
        $has_access = true;
        $is_admin_role = true;
        break;
    }
    if ($role_id == 6) {
        $has_access = true;
    }
}

$can_edit = $is_admin_role;
$can_delete = $is_admin_role;
$can_batch_delete = $is_admin_role;

if (!$has_access) {
    $_SESSION['error'] = "You don't have permission to view this page.";
    header("Location: ../404.php");
    exit();
}

// Handle Edit Transaction
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // Edit transaction
    if ($_POST['action'] == 'edit_transaction' && $can_edit) {
        $transaction_id = intval($_POST['transaction_id']);
        $new_quantity = intval($_POST['new_quantity']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        
        // Get original transaction details
        $original_sql = "SELECT * FROM equipment_transactions WHERE id = ?";
        $stmt = $conn->prepare($original_sql);
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $original = $stmt->get_result()->fetch_assoc();
        
        if ($original) {
            // Calculate the difference
            $quantity_diff = $new_quantity - $original['quantity'];
            $equipment_id = $original['equipment_id'];
            
            // Get current equipment quantity
            $equipment_sql = "SELECT quantity FROM sports_equipment WHERE id = ?";
            $stmt = $conn->prepare($equipment_sql);
            $stmt->bind_param("i", $equipment_id);
            $stmt->execute();
            $equipment = $stmt->get_result()->fetch_assoc();
            
            // Calculate new equipment quantity
            $new_equipment_qty = $equipment['quantity'];
            
            if ($original['transaction_type'] == 'IN') {
                // For IN transactions: original added quantity, now adjust
                $new_equipment_qty = $equipment['quantity'] - $original['quantity'] + $new_quantity;
            } else {
                // For OUT transactions: original removed quantity, now adjust
                $new_equipment_qty = $equipment['quantity'] + $original['quantity'] - $new_quantity;
            }
            
            if ($new_equipment_qty < 0) {
                $_SESSION['error'] = "Cannot edit transaction: Would result in negative stock!";
                header("Location: sports_store_transactions.php");
                exit();
            }
            
            // Update equipment quantity
            $update_equipment = "UPDATE sports_equipment SET quantity = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_equipment);
            $stmt->bind_param("ii", $new_equipment_qty, $equipment_id);
            $stmt->execute();
            
            // Update transaction
            $update_sql = "UPDATE equipment_transactions SET quantity = ?, reason = ?, created_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("isi", $new_quantity, $reason, $transaction_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Transaction updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating transaction: " . $conn->error;
            }
        }
        header("Location: sports_store_transactions.php");
        exit();
    }
    
    // Delete single transaction (with stock revert)
    if ($_POST['action'] == 'delete_transaction' && $can_delete) {
        $transaction_id = intval($_POST['transaction_id']);
        
        // Get transaction details to revert stock
        $transaction_sql = "SELECT * FROM equipment_transactions WHERE id = ?";
        $stmt = $conn->prepare($transaction_sql);
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $transaction = $stmt->get_result()->fetch_assoc();
        
        if ($transaction) {
            $equipment_id = $transaction['equipment_id'];
            $quantity = $transaction['quantity'];
            $type = $transaction['transaction_type'];
            
            // Get current equipment quantity
            $equipment_sql = "SELECT quantity, item_name FROM sports_equipment WHERE id = ?";
            $stmt = $conn->prepare($equipment_sql);
            $stmt->bind_param("i", $equipment_id);
            $stmt->execute();
            $equipment = $stmt->get_result()->fetch_assoc();
            
            if ($equipment) {
                // Reverse the transaction
                if ($type == 'IN') {
                    $new_quantity = $equipment['quantity'] - $quantity;
                } else {
                    $new_quantity = $equipment['quantity'] + $quantity;
                }
                
                if ($new_quantity < 0) {
                    $_SESSION['error'] = "Cannot delete transaction: Would result in negative stock for " . $equipment['item_name'] . "!";
                    header("Location: sports_store_transactions.php");
                    exit();
                }
                
                // Update equipment quantity
                $update_equipment = "UPDATE sports_equipment SET quantity = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($update_equipment);
                $stmt->bind_param("ii", $new_quantity, $equipment_id);
                $stmt->execute();
            }
            
            // Delete transaction
            $delete_sql = "DELETE FROM equipment_transactions WHERE id = ?";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param("i", $transaction_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Transaction deleted and stock reverted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting transaction: " . $conn->error;
            }
        }
        header("Location: sports_store_transactions.php");
        exit();
    }
    
    // Batch delete transactions (selected or all)
    if ($_POST['action'] == 'batch_delete_transactions' && $can_batch_delete) {
        $delete_type = $_POST['delete_type'];
        $transaction_ids = isset($_POST['transaction_ids']) ? $_POST['transaction_ids'] : [];
        
        // Start transaction for data integrity
        $conn->begin_transaction();
        
        try {
            if ($delete_type == 'selected' && !empty($transaction_ids)) {
                // Delete selected transactions
                $placeholders = implode(',', array_fill(0, count($transaction_ids), '?'));
                $select_sql = "SELECT * FROM equipment_transactions WHERE id IN ($placeholders)";
                $stmt = $conn->prepare($select_sql);
                $stmt->bind_param(str_repeat('i', count($transaction_ids)), ...$transaction_ids);
                $stmt->execute();
                $transactions_to_delete = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Process each transaction to revert stock
                foreach ($transactions_to_delete as $transaction) {
                    $equipment_id = $transaction['equipment_id'];
                    $quantity = $transaction['quantity'];
                    $type = $transaction['transaction_type'];
                    
                    // Get current equipment quantity
                    $equipment_sql = "SELECT quantity FROM sports_equipment WHERE id = ? FOR UPDATE";
                    $stmt = $conn->prepare($equipment_sql);
                    $stmt->bind_param("i", $equipment_id);
                    $stmt->execute();
                    $equipment = $stmt->get_result()->fetch_assoc();
                    
                    if ($equipment) {
                        if ($type == 'IN') {
                            $new_quantity = $equipment['quantity'] - $quantity;
                        } else {
                            $new_quantity = $equipment['quantity'] + $quantity;
                        }
                        
                        if ($new_quantity < 0) {
                            throw new Exception("Cannot delete: Would result in negative stock for equipment ID: " . $equipment_id);
                        }
                        
                        // Update equipment quantity
                        $update_equipment = "UPDATE sports_equipment SET quantity = ?, updated_at = NOW() WHERE id = ?";
                        $stmt = $conn->prepare($update_equipment);
                        $stmt->bind_param("ii", $new_quantity, $equipment_id);
                        $stmt->execute();
                    }
                }
                
                // Delete the selected transactions
                $delete_sql = "DELETE FROM equipment_transactions WHERE id IN ($placeholders)";
                $stmt = $conn->prepare($delete_sql);
                $stmt->bind_param(str_repeat('i', count($transaction_ids)), ...$transaction_ids);
                $stmt->execute();
                
                $_SESSION['success'] = count($transaction_ids) . " transaction(s) deleted successfully!";
                
            } elseif ($delete_type == 'all') {
                // Get all transactions to revert stock
                $all_sql = "SELECT * FROM equipment_transactions";
                $all_result = mysqli_query($conn, $all_sql);
                $all_transactions = mysqli_fetch_all($all_result, MYSQLI_ASSOC);
                
                // Group transactions by equipment to calculate net effect
                $equipment_changes = [];
                foreach ($all_transactions as $transaction) {
                    $equipment_id = $transaction['equipment_id'];
                    $quantity = $transaction['quantity'];
                    $type = $transaction['transaction_type'];
                    
                    if (!isset($equipment_changes[$equipment_id])) {
                        $equipment_changes[$equipment_id] = 0;
                    }
                    
                    if ($type == 'IN') {
                        $equipment_changes[$equipment_id] -= $quantity;
                    } else {
                        $equipment_changes[$equipment_id] += $quantity;
                    }
                }
                
                // Apply the reversals
                foreach ($equipment_changes as $equipment_id => $net_change) {
                    $equipment_sql = "SELECT quantity FROM sports_equipment WHERE id = ? FOR UPDATE";
                    $stmt = $conn->prepare($equipment_sql);
                    $stmt->bind_param("i", $equipment_id);
                    $stmt->execute();
                    $equipment = $stmt->get_result()->fetch_assoc();
                    
                    if ($equipment) {
                        $new_quantity = $equipment['quantity'] + $net_change;
                        
                        if ($new_quantity < 0) {
                            throw new Exception("Cannot delete: Would result in negative stock for equipment ID: " . $equipment_id);
                        }
                        
                        // Reset quantity to original before any transactions
                        $update_equipment = "UPDATE sports_equipment SET quantity = ?, updated_at = NOW() WHERE id = ?";
                        $stmt = $conn->prepare($update_equipment);
                        $stmt->bind_param("ii", $new_quantity, $equipment_id);
                        $stmt->execute();
                    }
                }
                
                // Delete all transactions
                $truncate_sql = "DELETE FROM equipment_transactions";
                mysqli_query($conn, $truncate_sql);
                
                $_SESSION['success'] = "All transactions have been deleted successfully!";
            } else {
                $_SESSION['error'] = "No transactions selected for deletion.";
            }
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: sports_store_transactions.php");
        exit();
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$where_conditions = [];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "e.item_name LIKE ?";
    $params[] = "%$search%";
    $param_types .= "s";
}

if (!empty($type)) {
    $where_conditions[] = "t.transaction_type = ?";
    $params[] = $type;
    $param_types .= "s";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(t.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(t.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

$where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

$transactions_sql = "SELECT t.*, e.item_name, e.category, e.unit, 
                     CONCAT(a.first_name, ' ', a.last_name) as performed_by_name
                     FROM equipment_transactions t
                     LEFT JOIN sports_equipment e ON t.equipment_id = e.id
                     LEFT JOIN admins a ON t.performed_by = a.id
                     $where_clause
                     ORDER BY t.created_at DESC";

$stmt = $conn->prepare($transactions_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$transactions_result = $stmt->get_result();

// Get summary stats
$summary_sql = "SELECT 
    SUM(CASE WHEN transaction_type = 'IN' THEN quantity ELSE 0 END) as total_in,
    SUM(CASE WHEN transaction_type = 'OUT' THEN quantity ELSE 0 END) as total_out,
    COUNT(*) as total_transactions
    FROM equipment_transactions";
$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result);

// Get total count for current filter
$total_records = mysqli_num_rows($transactions_result);
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="page-title">
                    <i class="fas fa-history me-2"></i>
                    Transaction History
                </h2>
                <p class="text-muted">View, edit, and manage all stock movement records</p>
            </div>
            <div>
                <a href="sports_store.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Store
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-exchange-alt" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo $summary['total_transactions'] ?? 0; ?></h3>
                    <p>Total Transactions</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-arrow-down" style="color: #28a745;"></i></div>
                    <h3><?php echo $summary['total_in'] ?? 0; ?></h3>
                    <p>Items Added (IN)</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-arrow-up" style="color: #dc3545;"></i></div>
                    <h3><?php echo $summary['total_out'] ?? 0; ?></h3>
                    <p>Items Removed (OUT)</p>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search Equipment</label>
                        <input type="text" name="search" class="form-control" placeholder="Item name..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Transaction Type</label>
                        <select name="type" class="form-select">
                            <option value="">All</option>
                            <option value="IN" <?php echo $type == 'IN' ? 'selected' : ''; ?>>Stock IN (Added)</option>
                            <option value="OUT" <?php echo $type == 'OUT' ? 'selected' : ''; ?>>Stock OUT (Removed)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk Actions Bar -->
        <?php if ($transactions_result && mysqli_num_rows($transactions_result) > 0 && $can_batch_delete): ?>
        <div class="card mb-4" id="bulkActionsBar" style="display: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-check-circle text-primary me-2"></i>
                        <span id="selectedCount">0</span> transaction(s) selected
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-warning me-2" id="selectAllBtn">
                            <i class="fas fa-check-double me-1"></i>Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" id="batchDeleteBtn">
                            <i class="fas fa-trash-alt me-1"></i>Delete Selected
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary ms-2" id="clearSelectionBtn">
                            <i class="fas fa-times me-1"></i>Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Transactions Table -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Transaction Records</h5>
                    <div>
                        <span class="badge bg-light text-dark">Total: <?php echo $total_records; ?> records</span>
                        <?php if ($can_batch_delete && $total_records > 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-light ms-2" id="deleteAllBtn">
                                <i class="fas fa-trash-alt me-1"></i>Delete All History
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($transactions_result && mysqli_num_rows($transactions_result) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="transactionsTable">
                            <thead class="table-light">
                                <tr style="text-align: center;">
                                    <?php if ($can_batch_delete): ?>
                                        <th width="40">
                                            <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
                                        </th>
                                    <?php endif; ?>
                                    <th>Date & Time</th>
                                    <th>Equipment</th>
                                    <th>Category</th>
                                    <th>Type</th>
                                    <th>Quantity</th>
                                    <th>Previous Qty</th>
                                    <th>New Qty</th>
                                    <th>Reason</th>
                                    <th>Performed By</th>
                                    <?php if ($can_edit || $can_delete): ?>
                                        <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($txn = mysqli_fetch_assoc($transactions_result)): ?>
                                    <tr style="text-align: center;" id="transaction-row-<?php echo $txn['id']; ?>">
                                        <?php if ($can_batch_delete): ?>
                                            <td>
                                                <input type="checkbox" class="transaction-checkbox form-check-input" value="<?php echo $txn['id']; ?>">
                                            </td>
                                        <?php endif; ?>
                                        <td><?php echo date('M d, Y H:i', strtotime($txn['created_at'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($txn['item_name']); ?></strong></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($txn['category']); ?></span></td>
                                        <td>
                                            <?php if ($txn['transaction_type'] == 'IN'): ?>
                                                <span class="badge bg-success">+ Stock IN</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">- Stock OUT</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $txn['quantity']; ?> <?php echo $txn['unit']; ?></td>
                                        <td><?php echo $txn['previous_quantity']; ?></td>
                                        <td><?php echo $txn['new_quantity']; ?></td>
                                        <td style="text-align: left; max-width: 200px;"><?php echo htmlspecialchars($txn['reason']); ?></td>
                                        <td><?php echo htmlspecialchars($txn['performed_by_name'] ?? 'System'); ?></td>
                                        <?php if ($can_edit || $can_delete): ?>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <?php if ($can_edit): ?>
                                                        <button class="btn btn-outline-primary edit-transaction" 
                                                                data-id="<?php echo $txn['id']; ?>"
                                                                data-item-name="<?php echo htmlspecialchars($txn['item_name']); ?>"
                                                                data-type="<?php echo $txn['transaction_type']; ?>"
                                                                data-quantity="<?php echo $txn['quantity']; ?>"
                                                                data-reason="<?php echo htmlspecialchars($txn['reason']); ?>"
                                                                title="Edit Transaction">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($can_delete): ?>
                                                        <button class="btn btn-outline-danger delete-transaction" 
                                                                data-id="<?php echo $txn['id']; ?>"
                                                                data-item-name="<?php echo htmlspecialchars($txn['item_name']); ?>"
                                                                data-type="<?php echo $txn['transaction_type']; ?>"
                                                                data-quantity="<?php echo $txn['quantity']; ?>"
                                                                title="Delete Transaction">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <h5>No transactions found</h5>
                        <p class="text-muted">Stock movements will appear here when you update inventory.</p>
                        <a href="sports_store.php" class="btn btn-primary mt-3">
                            <i class="fas fa-plus me-2"></i>Add Stock Movement
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Transaction Modal -->
<div class="modal fade" id="editTransactionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Transaction</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_transaction">
                    <input type="hidden" name="transaction_id" id="edit_transaction_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Equipment</label>
                        <input type="text" id="edit_item_name" class="form-control" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Transaction Type</label>
                        <input type="text" id="edit_transaction_type" class="form-control" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Original Quantity</label>
                        <input type="number" id="edit_original_quantity" class="form-control" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Quantity *</label>
                        <input type="number" name="new_quantity" id="edit_new_quantity" class="form-control" min="1" required>
                        <small class="text-muted">Enter the corrected quantity for this transaction</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason / Notes *</label>
                        <textarea name="reason" id="edit_reason" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Note:</strong> Editing this transaction will automatically adjust the current stock level to reflect the change.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
// Edit Transaction Modal
const editButtons = document.querySelectorAll('.edit-transaction');
editButtons.forEach(button => {
    button.addEventListener('click', function() {
        const transactionId = this.getAttribute('data-id');
        const itemName = this.getAttribute('data-item-name');
        const transactionType = this.getAttribute('data-type');
        const quantity = this.getAttribute('data-quantity');
        const reason = this.getAttribute('data-reason');
        
        document.getElementById('edit_transaction_id').value = transactionId;
        document.getElementById('edit_item_name').value = itemName;
        
        let typeDisplay = transactionType == 'IN' ? 'Stock IN (Added)' : 'Stock OUT (Removed)';
        document.getElementById('edit_transaction_type').value = typeDisplay;
        document.getElementById('edit_original_quantity').value = quantity;
        document.getElementById('edit_new_quantity').value = quantity;
        document.getElementById('edit_reason').value = reason;
        
        const modal = new bootstrap.Modal(document.getElementById('editTransactionModal'));
        modal.show();
    });
});

// Delete Single Transaction Confirmation
const deleteButtons = document.querySelectorAll('.delete-transaction');
deleteButtons.forEach(button => {
    button.addEventListener('click', function() {
        const transactionId = this.getAttribute('data-id');
        const itemName = this.getAttribute('data-item-name');
        const transactionType = this.getAttribute('data-type');
        const quantity = this.getAttribute('data-quantity');
        
        Swal.fire({
            title: 'Delete Transaction?',
            html: `Are you sure you want to delete this transaction?<br><br>
                   <strong>Item:</strong> ${itemName}<br>
                   <strong>Type:</strong> ${transactionType == 'IN' ? 'Stock IN' : 'Stock OUT'}<br>
                   <strong>Quantity:</strong> ${quantity}<br><br>
                   This will revert the stock level to before this transaction was recorded.<br>
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
                    <input type="hidden" name="action" value="delete_transaction">
                    <input type="hidden" name="transaction_id" value="${transactionId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});

// Batch Delete Functionality
<?php if ($can_batch_delete && $total_records > 0): ?>
const checkboxes = document.querySelectorAll('.transaction-checkbox');
const selectAllCheckbox = document.getElementById('selectAllCheckbox');
const bulkActionsBar = document.getElementById('bulkActionsBar');
const selectedCountSpan = document.getElementById('selectedCount');
const selectAllBtn = document.getElementById('selectAllBtn');
const clearSelectionBtn = document.getElementById('clearSelectionBtn');
const batchDeleteBtn = document.getElementById('batchDeleteBtn');
const deleteAllBtn = document.getElementById('deleteAllBtn');

function updateSelectedCount() {
    const selected = document.querySelectorAll('.transaction-checkbox:checked');
    const count = selected.length;
    selectedCountSpan.textContent = count;
    
    if (count > 0) {
        bulkActionsBar.style.display = 'block';
    } else {
        bulkActionsBar.style.display = 'none';
    }
    
    if (selectAllCheckbox) {
        const allCheckboxes = document.querySelectorAll('.transaction-checkbox');
        selectAllCheckbox.checked = count === allCheckboxes.length && allCheckboxes.length > 0;
    }
}

if (checkboxes.length > 0) {
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
}

if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });
}

if (selectAllBtn) {
    selectAllBtn.addEventListener('click', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        if (selectAllCheckbox) selectAllCheckbox.checked = true;
        updateSelectedCount();
    });
}

if (clearSelectionBtn) {
    clearSelectionBtn.addEventListener('click', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        if (selectAllCheckbox) selectAllCheckbox.checked = false;
        updateSelectedCount();
    });
}

if (batchDeleteBtn) {
    batchDeleteBtn.addEventListener('click', function() {
        const selected = document.querySelectorAll('.transaction-checkbox:checked');
        const selectedIds = Array.from(selected).map(cb => cb.value);
        
        if (selectedIds.length === 0) {
            Swal.fire('Warning', 'Please select at least one transaction to delete.', 'warning');
            return;
        }
        
        Swal.fire({
            title: 'Delete Selected Transactions?',
            html: `Are you sure you want to delete <strong>${selectedIds.length}</strong> transaction(s)?<br><br>
                   This will revert the stock levels for all selected transactions.<br>
                   <span class="text-danger">This action cannot be undone!</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete them!'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                form.innerHTML = `
                    <input type="hidden" name="action" value="batch_delete_transactions">
                    <input type="hidden" name="delete_type" value="selected">
                    ${selectedIds.map(id => `<input type="hidden" name="transaction_ids[]" value="${id}">`).join('')}
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
}

if (deleteAllBtn) {
    deleteAllBtn.addEventListener('click', function() {
        Swal.fire({
            title: 'Delete All Transaction History?',
            html: `Are you sure you want to delete <strong>ALL <?php echo $total_records; ?> transactions</strong>?<br><br>
                   This will reset the stock levels to their original state before any transactions were recorded.<br>
                   <span class="text-danger">This action cannot be undone!</span>`,
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete all!'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                form.innerHTML = `
                    <input type="hidden" name="action" value="batch_delete_transactions">
                    <input type="hidden" name="delete_type" value="all">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
}
<?php endif; ?>

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

#bulkActionsBar {
    transition: all 0.3s ease;
    position: sticky;
    top: 0;
    z-index: 100;
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