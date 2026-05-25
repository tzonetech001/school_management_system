<?php
// productions.php
session_start();
require_once '../controller/db_connect.php';

// Get all productions with creator info
$sql = "SELECT p.*, 
        CONCAT(a.first_name, ' ', a.last_name) as created_by_name,
        a.email as creator_email
        FROM productions p
        LEFT JOIN admins a ON p.created_by = a.id
        ORDER BY p.production_date DESC, p.created_at DESC";

$result = mysqli_query($conn, $sql);
$productions = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $productions[] = $row;
    }
}

// Get categories
$category_sql = "SELECT * FROM production_categories WHERE status = 1 ORDER BY category_name";
$category_result = mysqli_query($conn, $category_sql);
$categories = [];
if ($category_result && mysqli_num_rows($category_result) > 0) {
    while ($row = mysqli_fetch_assoc($category_result)) {
        $categories[] = $row;
    }
}

// Handle production deletion
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    $current_admin_id = $_SESSION['admin_id'];
    
    // First delete uses
    $delete_uses_sql = "DELETE FROM production_uses WHERE production_id = $id";
    mysqli_query($conn, $delete_uses_sql);
    
    // Then delete production
    $delete_sql = "DELETE FROM productions WHERE id = $id";
    if (mysqli_query($conn, $delete_sql)) {
        $_SESSION['success'] = "Production record deleted successfully!";
        header("Location: productions.php");
        exit();
    } else {
        $_SESSION['error'] = "Error deleting record: " . mysqli_error($conn);
    }
}

// Handle status toggle (soft delete alternative)
if (isset($_GET['toggle_archive'])) {
    $id = mysqli_real_escape_string($conn, $_GET['toggle_archive']);
    
    // Note: For now we're deleting, but you can add an 'is_active' column for archiving
    $_SESSION['success'] = "Archive functionality can be implemented with an is_active column";
    header("Location: productions.php");
    exit();
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">Production & Creativity Management</h2>
            <div class="dropdown d-md-block d-none">
                <button class="btn btn-primary dropdown-toggle" type="button" id="actionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog me-2"></i>Actions
                </button>
                <ul class="dropdown-menu" aria-labelledby="actionsDropdown">
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addProductionModal">
                        <i class="fas fa-plus-circle me-2"></i>Add Production
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="report_production.php">
                        <i class="fas fa-download me-2"></i>Export Report
                    </a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#manageCategoriesModal">
                        <i class="fas fa-tags me-2"></i>Manage Categories
                    </a></li>
                </ul>
            </div>
            
            <!-- Mobile Actions Button -->
            <div class="dropdown d-md-none">
                <button class="btn btn-primary" type="button" id="mobileActionsBtn" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="mobileActionsBtn">
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addProductionModal">
                        <i class="fas fa-plus-circle me-2"></i>Add Production
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="report_production.php">
                        <i class="fas fa-download me-2"></i>Export Report
                    </a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#manageCategoriesModal">
                        <i class="fas fa-tags me-2"></i>Manage Categories
                    </a></li>
                </ul>
            </div>
        </div>

        <!-- SweetAlert2 Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div id="successMessage" data-message="<?php echo htmlspecialchars($_SESSION['success']); ?>"></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div id="errorMessage" data-message="<?php echo htmlspecialchars($_SESSION['error']); ?>"></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-seedling" style="color: #28a745;"></i>
                    </div>
                    <h3><?php echo count($productions); ?></h3>
                    <p>Total Productions</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-shopping-cart" style="color: #ffc107;"></i>
                    </div>
                    <h3>
                        <?php 
                        $shop_count = 0;
                        foreach ($productions as $p) {
                            if ($p['category'] == 'shop') $shop_count++;
                        }
                        echo $shop_count;
                        ?>
                    </h3>
                    <p>Shop Items</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-tractor" style="color: #17a2b8;"></i>
                    </div>
                    <h3>
                        <?php 
                        $farm_count = 0;
                        foreach ($productions as $p) {
                            if ($p['category'] == 'farm') $farm_count++;
                        }
                        echo $farm_count;
                        ?>
                    </h3>
                    <p>Farm Products</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-bee" style="color: #fd7e14;"></i>
                    </div>
                    <h3>
                        <?php 
                        $bee_count = 0;
                        foreach ($productions as $p) {
                            if ($p['category'] == 'beekeeping') $bee_count++;
                        }
                        echo $bee_count;
                        ?>
                    </h3>
                    <p>Beekeeping</p>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search productions...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="categoryFilter" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category_name']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($cat['category_name'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="month" id="monthFilter" class="form-control" value="<?php echo date('Y-m'); ?>">
                    </div>
                    <div class="col-md-2">
                        <button id="resetFilters" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Productions Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Productions</h5>
                <span style="float:right;" class="badge bg-light text-dark ms-2">
                    <i class="fas fa-plus"></i>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#addProductionModal" style="color: #333;">
                        Add New Production
                    </a>
                </span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="productionsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($productions)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No productions found. 
                                        <a href="#" data-bs-toggle="modal" data-bs-target="#addProductionModal">Add first production</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($productions as $prod): ?>
                                <tr>
                                    <td>
                                        <span class="badge category-badge" style="background-color: <?php echo getCategoryColor($prod['category']); ?>">
                                            <i class="<?php echo getCategoryIcon($prod['category']); ?> me-1"></i>
                                            <?php echo ucfirst(htmlspecialchars($prod['category'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($prod['production_type']); ?></strong>
                                        <?php if (!empty($prod['unit'])): ?>
                                            <small class="text-muted">(<?php echo htmlspecialchars($prod['unit']); ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($prod['quantity'])): ?>
                                            <span class="fw-bold"><?php echo htmlspecialchars($prod['quantity']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($prod['amount'])): ?>
                                            <span class="fw-bold text-success">
                                                <?php echo number_format($prod['amount'], 2); ?> <?php echo htmlspecialchars($prod['currency']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($prod['production_date'])); ?>
                                        <div class="small text-muted">
                                            By: <?php echo htmlspecialchars($prod['created_by_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($prod['short_note'])): ?>
                                            <span title="<?php echo htmlspecialchars($prod['short_note']); ?>">
                                                <?php echo substr(htmlspecialchars($prod['short_note']), 0, 30); ?>
                                                <?php if (strlen($prod['short_note']) > 30): ?>...<?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No notes</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-info view-production" 
                                                    data-bs-toggle="modal" data-bs-target="#viewProductionModal"
                                                    data-id="<?php echo $prod['id']; ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-warning edit-production"
                                                    data-id="<?php echo $prod['id']; ?>"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-primary add-uses"
                                                    data-id="<?php echo $prod['id']; ?>"
                                                    data-type="<?php echo htmlspecialchars($prod['production_type']); ?>"
                                                    data-quantity="<?php echo htmlspecialchars($prod['quantity']); ?>"
                                                    title="Add Uses">
                                                <i class="fas fa-utensils"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger delete-production" 
                                                    data-id="<?php echo $prod['id']; ?>"
                                                    data-type="<?php echo htmlspecialchars($prod['production_type']); ?>"
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

<!-- Add Production Modal -->
<div class="modal fade" id="addProductionModal" tabindex="-1" aria-labelledby="addProductionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #28a745; color: white;">
                <h5 class="modal-title" id="addProductionModalLabel">Add New Production</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process_production.php" method="POST" id="productionForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category_name']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($cat['category_name'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="production_type" class="form-label">Type of Production <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="production_type" name="production_type" required
                                   placeholder="e.g., Maize, Honey, Soap, Eggs...">
                        </div>
                        <div class="col-md-4">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" step="0.01"
                                   placeholder="Optional">
                        </div>
                        <div class="col-md-4">
                            <label for="unit" class="form-label">Unit</label>
                            <input type="text" class="form-control" id="unit" name="unit" 
                                   placeholder="e.g., kg, liters, pieces">
                        </div>
                        <div class="col-md-4">
                            <label for="amount" class="form-label">Amount (Value)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01"
                                       placeholder="Optional">
                                <span class="input-group-text">TZS</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="production_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="production_date" name="production_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="short_note" class="form-label">Short Note</label>
                            <textarea class="form-control" id="short_note" name="short_note" rows="2" 
                                      placeholder="Brief description or notes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" name="add_production">
                        <i class="fas fa-save me-2"></i>Save Production
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Production Modal -->
<div class="modal fade" id="viewProductionModal" tabindex="-1" aria-labelledby="viewProductionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3B9DB3; color: white;">
                <h5 class="modal-title" id="viewProductionModalLabel">Production Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="productionDetails">
                <!-- Production details will be loaded here via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Uses Modal -->
<div class="modal fade" id="addUsesModal" tabindex="-1" aria-labelledby="addUsesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #6f42c1; color: white;">
                <h5 class="modal-title" id="addUsesModalLabel">Add Uses for Production</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process_production.php" method="POST" id="usesForm">
                <input type="hidden" id="use_production_id" name="production_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <h6 id="productionTitle"></h6>
                        <small class="text-muted" id="availableQuantity"></small>
                    </div>
                    <div class="mb-3">
                        <label for="use_description" class="form-label">Use Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="use_description" name="use_description" rows="3" 
                                  placeholder="How was this product used? e.g., School lunch, Sold to staff, Donated..." required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="used_quantity" class="form-label">Quantity Used <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="used_quantity" name="used_quantity" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label for="use_date" class="form-label">Date Used <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="use_date" name="use_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="used_by" class="form-label">Used By</label>
                            <input type="text" class="form-control" id="used_by" name="used_by" 
                                   placeholder="e.g., Kitchen, Staff, Students...">
                        </div>
                        <div class="col-md-6">
                            <label for="use_notes" class="form-label">Notes</label>
                            <input type="text" class="form-control" id="use_notes" name="notes" 
                                   placeholder="Additional notes...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="add_use">
                        <i class="fas fa-plus-circle me-2"></i>Add Use
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manage Categories Modal -->
<div class="modal fade" id="manageCategoriesModal" tabindex="-1" aria-labelledby="manageCategoriesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #fd7e14; color: white;">
                <h5 class="modal-title" id="manageCategoriesModalLabel">Manage Categories</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="categoriesList">
                    <!-- Categories will be loaded here -->
                </div>
                <hr>
                <h6>Add New Category</h6>
                <form id="addCategoryForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="new_category_name" 
                                   placeholder="Category name" required>
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="new_category_unit" 
                                   placeholder="Default unit (e.g., kg)">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                <h5 class="mb-3">Delete Production Record?</h5>
                <p class="mb-2"><strong id="deleteProductionType"></strong></p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> All usage records for this production will also be deleted.
                </div>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDelete" class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Delete
                </a>
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
            confirmButtonColor: '#28a745',
            timer: 3000,
            timerProgressBar: true,
            position: 'center'
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
            position: 'center'
        });
    }
});

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const rows = document.querySelectorAll('#productionsTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    });
});

// Filter functionality
document.getElementById('categoryFilter').addEventListener('change', filterTable);
document.getElementById('monthFilter').addEventListener('change', filterTable);

function filterTable() {
    const category = document.getElementById('categoryFilter').value;
    const month = document.getElementById('monthFilter').value;
    const rows = document.querySelectorAll('#productionsTable tbody tr');
    
    rows.forEach(row => {
        if (row.style.display === 'none') return;
        
        const rowCategory = row.cells[0].textContent.toLowerCase().trim();
        const rowDate = row.cells[4].textContent.trim();
        
        const showCategory = !category || rowCategory.includes(category.toLowerCase());
        const showMonth = !month || rowDate.includes(getMonthName(month));
        
        row.style.display = (showCategory && showMonth) ? '' : 'none';
    });
}

function getMonthName(monthString) {
    const date = new Date(monthString + '-01');
    return date.toLocaleString('default', { month: 'short' });
}

// Reset filters
document.getElementById('resetFilters').addEventListener('click', function() {
    document.getElementById('searchInput').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('monthFilter').value = '';
    
    const rows = document.querySelectorAll('#productionsTable tbody tr');
    rows.forEach(row => {
        row.style.display = '';
    });
});

// Delete confirmation
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete-production');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const prodId = this.getAttribute('data-id');
            const prodType = this.getAttribute('data-type');
            
            document.getElementById('deleteProductionType').textContent = prodType;
            document.getElementById('confirmDelete').href = `productions.php?delete=${prodId}`;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
            deleteModal.show();
        });
    });
});

// Add uses functionality
document.addEventListener('DOMContentLoaded', function() {
    const useButtons = document.querySelectorAll('.add-uses');
    useButtons.forEach(button => {
        button.addEventListener('click', function() {
            const prodId = this.getAttribute('data-id');
            const prodType = this.getAttribute('data-type');
            const prodQuantity = this.getAttribute('data-quantity') || 'N/A';
            
            document.getElementById('use_production_id').value = prodId;
            document.getElementById('productionTitle').textContent = prodType;
            document.getElementById('availableQuantity').textContent = `Available: ${prodQuantity}`;
            
            const usesModal = new bootstrap.Modal(document.getElementById('addUsesModal'));
            usesModal.show();
        });
    });
});

// Load production details
document.addEventListener('DOMContentLoaded', function() {
    const viewButtons = document.querySelectorAll('.view-production');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const prodId = this.getAttribute('data-id');
            
            // Show loading
            document.getElementById('productionDetails').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading production details...</p>
                </div>
            `;
            
            // Fetch production details via AJAX
            fetch(`get_production.php?id=${prodId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('productionDetails').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('productionDetails').innerHTML = 
                        '<div class="alert alert-danger">Error loading production details.</div>';
                });
        });
    });
});

// Load categories for management
document.addEventListener('DOMContentLoaded', function() {
    const categoriesModal = document.getElementById('manageCategoriesModal');
    if (categoriesModal) {
        categoriesModal.addEventListener('show.bs.modal', function() {
            loadCategories();
        });
    }
});

function loadCategories() {
    fetch('get_categories.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('categoriesList').innerHTML = data;
        });
}

// Add new category
document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const name = document.getElementById('new_category_name').value;
    const unit = document.getElementById('new_category_unit').value;
    
    fetch('add_category.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `name=${encodeURIComponent(name)}&unit=${encodeURIComponent(unit)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Success!',
                text: 'Category added successfully',
                icon: 'success',
                confirmButtonColor: '#28a745'
            });
            loadCategories();
            document.getElementById('addCategoryForm').reset();
        } else {
            Swal.fire({
                title: 'Error!',
                text: data.message,
                icon: 'error',
                confirmButtonColor: '#d33'
            });
        }
    });
});

// Edit production
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.edit-production');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const prodId = this.getAttribute('data-id');
            
            // Load production data into form
            fetch(`get_production.php?id=${prodId}&edit=true`)
                .then(response => response.json())
                .then(data => {
                    if (data) {
                        // Populate form
                        document.getElementById('category').value = data.category;
                        document.getElementById('production_type').value = data.production_type;
                        document.getElementById('quantity').value = data.quantity;
                        document.getElementById('unit').value = data.unit;
                        document.getElementById('amount').value = data.amount;
                        document.getElementById('production_date').value = data.production_date;
                        document.getElementById('short_note').value = data.short_note;
                        
                        // Update form for editing
                        const form = document.getElementById('productionForm');
                        form.action = 'process_production.php?edit=' + prodId;
                        const submitBtn = form.querySelector('button[type="submit"]');
                        submitBtn.innerHTML = '<i class="fas fa-edit me-2"></i>Update Production';
                        submitBtn.classList.remove('btn-success');
                        submitBtn.classList.add('btn-warning');
                        
                        // Show modal
                        const modal = new bootstrap.Modal(document.getElementById('addProductionModal'));
                        modal.show();
                    }
                });
        });
    });
});
</script>

<style>
    .card-header{
         background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);
    }
.category-badge {
    font-size: 0.8rem;
    padding: 5px 10px;
    border-radius: 20px;
}

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
    background: #28a745;
}

.stats-card.simple-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.table th {
    font-weight: 600;
    color: #333;
    background-color: rgba(40, 167, 69, 0.05);
    border-bottom: 2px solid rgba(40, 167, 69, 0.2);
    padding: 12px 8px;
}

.btn-group .btn {
    margin-right: 2px;
}

@media (max-width: 768px) {
    .btn-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .btn-group .btn {
        margin-right: 0;
        width: 100%;
    }
}
</style>

<?php
function getCategoryColor($category) {
    $colors = [
        'shop' => '#ffc107',
        'farm' => '#28a745',
        'beekeeping' => '#fd7e14',
        'soap' => '#17a2b8',
        'fish' => '#20c997',
        'hen' => '#e83e8c',
        'garden' => '#6f42c1'
    ];
    return $colors[$category] ?? '#6c757d';
}

function getCategoryIcon($category) {
    $icons = [
        'shop' => 'fas fa-shopping-cart',
        'farm' => 'fas fa-tractor',
        'beekeeping' => 'fas fa-bee',
        'soap' => 'fas fa-soap',
        'fish' => 'fas fa-fish',
        'hen' => 'fas fa-egg',
        'garden' => 'fas fa-seedling'
    ];
    return $icons[$category] ?? 'fas fa-box';
}
?>

<?php include '../controller/footer.php'; ?>