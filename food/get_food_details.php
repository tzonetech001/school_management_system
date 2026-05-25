<?php
// get_food_details.php
session_start();
require_once '../controller/db_connect.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">Item ID is required!</div>';
    exit();
}

$id = intval($_GET['id']);

// Validate ID
if ($id <= 0) {
    echo '<div class="alert alert-danger">Invalid item ID!</div>';
    exit();
}

// Fetch item from database
$sql = "SELECT * FROM food_stock WHERE id = $id";
$result = mysqli_query($conn, $sql);

// Check if query was successful
if (!$result) {
    echo '<div class="alert alert-danger">Database error: ' . mysqli_error($conn) . '</div>';
    exit();
}

// Check if item exists
if (mysqli_num_rows($result) === 0) {
    echo '<div class="alert alert-danger">Item not found!</div>';
    exit();
}

// Fetch the item data
$item = mysqli_fetch_assoc($result);

// Determine status color and icon
$status_info = [
    'available' => ['color' => 'success', 'icon' => 'check-circle', 'text' => 'Available'],
    'low' => ['color' => 'warning', 'icon' => 'exclamation-triangle', 'text' => 'Low Stock'],
    'out_of_stock' => ['color' => 'danger', 'icon' => 'times-circle', 'text' => 'Out of Stock']
];

$status = isset($item['status']) && isset($status_info[$item['status']]) 
    ? $status_info[$item['status']] 
    : $status_info['available'];
?>
<div class="row">
    <div class="col-md-4 text-center mb-4">
        <div class="item-icon">
            <i class="fas fa-utensils fa-4x text-primary"></i>
        </div>
        <h4 class="mt-3"><?php echo htmlspecialchars($item['item_name']); ?></h4>
        <span class="badge bg-<?php echo $status['color']; ?> p-2">
            <i class="fas fa-<?php echo $status['icon']; ?> me-1"></i>
            <?php echo $status['text']; ?>
        </span>
    </div>
    <div class="col-md-8">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label text-muted">Quantity</label>
                <div class="h5"><?php echo number_format($item['quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?></div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label text-muted">Unit</label>
                <div class="h5"><?php echo htmlspecialchars($item['unit']); ?></div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label text-muted">Date Added</label>
                <div class="h5"><?php echo date('F j, Y', strtotime($item['date_added'])); ?></div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label text-muted">Last Updated</label>
                <div class="h5"><?php echo date('F j, Y H:i', strtotime($item['updated_at'])); ?></div>
            </div>
        </div>
        
        <?php if (!empty($item['description'])): ?>
        <div class="mb-3">
            <label class="form-label text-muted">Description</label>
            <div class="p-3 bg-light rounded">
                <?php echo nl2br(htmlspecialchars($item['description'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <h6 class="text-muted mb-3">Item Information</h6>
            <div class="d-flex justify-content-between">
                <div>
                    <small class="text-muted">Item ID:</small>
                    <div>#<?php echo str_pad($item['id'], 5, '0', STR_PAD_LEFT); ?></div>
                </div>
                <div>
                    <small class="text-muted">Created:</small>
                    <div><?php echo date('M d, Y', strtotime($item['created_at'])); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>