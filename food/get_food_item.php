<?php
// get_food_item.php
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

// Available units
$available_units = ['kg', 'liters', 'bags', 'packets', 'pieces', 'cartons', 'boxes', 'cans', 'bottles', 'sacks'];
?>
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="edit_item_name" class="form-label">Item Name *</label>
        <input type="text" class="form-control" id="edit_item_name" name="item_name" 
               value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
    </div>
    <div class="col-md-3 mb-3">
        <label for="edit_quantity" class="form-label">Quantity *</label>
        <input type="number" class="form-control" id="edit_quantity" name="quantity" 
               value="<?php echo $item['quantity']; ?>" step="0.01" min="0.01" required>
    </div>
    <div class="col-md-3 mb-3">
        <label for="edit_unit" class="form-label">Unit *</label>
        <select class="form-select" id="edit_unit" name="unit" required>
            <option value="">Select Unit</option>
            <?php foreach ($available_units as $unit): ?>
                <option value="<?php echo htmlspecialchars($unit); ?>" 
                    <?php echo ($item['unit'] == $unit) ? 'selected' : ''; ?>>
                    <?php echo ucfirst(htmlspecialchars($unit)); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="edit_date_added" class="form-label">Date Added</label>
        <input type="date" class="form-control" id="edit_date_added" name="date_added" 
               value="<?php echo htmlspecialchars($item['date_added']); ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label for="edit_description" class="form-label">Description / Notes</label>
        <textarea class="form-control" id="edit_description" name="description" rows="2"><?php 
            echo htmlspecialchars($item['description'] ?? ''); 
        ?></textarea>
    </div>
</div>