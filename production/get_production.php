<?php
// get_production.php
session_start();
require_once '../controller/db_connect.php';

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Check if edit mode
    if (isset($_GET['edit'])) {
        $sql = "SELECT * FROM productions WHERE id = $id";
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $production = mysqli_fetch_assoc($result);
            echo json_encode($production);
        } else {
            echo json_encode(null);
        }
        exit();
    }
    
    // Regular view mode
    $sql = "SELECT p.*, 
            CONCAT(a.first_name, ' ', a.last_name) as created_by_name,
            a.email as creator_email,
            (SELECT SUM(used_quantity) FROM production_uses WHERE production_id = p.id) as total_used
            FROM productions p
            LEFT JOIN admins a ON p.created_by = a.id
            WHERE p.id = $id";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $production = mysqli_fetch_assoc($result);
        
        // Get uses history
        $uses_sql = "SELECT * FROM production_uses WHERE production_id = $id ORDER BY use_date DESC";
        $uses_result = mysqli_query($conn, $uses_sql);
        ?>
        
        <div class="production-details">
            <div class="row mb-4">
                <div class="col-md-8">
                    <h4><?php echo htmlspecialchars($production['production_type']); ?></h4>
                    <span class="badge category-badge" style="background-color: <?php echo getCategoryColor($production['category']); ?>">
                        <i class="<?php echo getCategoryIcon($production['category']); ?> me-1"></i>
                        <?php echo ucfirst(htmlspecialchars($production['category'])); ?>
                    </span>
                </div>
                <div class="col-md-4 text-end">
                    <h5 class="text-success">
                        <?php if (!empty($production['amount'])): ?>
                            <?php echo number_format($production['amount'], 2); ?> TZS
                        <?php else: ?>
                            <span class="text-muted">No value</span>
                        <?php endif; ?>
                    </h5>
                    <small class="text-muted">Date: <?php echo date('F j, Y', strtotime($production['production_date'])); ?></small>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="info-card">
                        <h6><i class="fas fa-info-circle me-2 text-primary"></i>Details</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Quantity:</strong></td>
                                <td>
                                    <?php if (!empty($production['quantity'])): ?>
                                        <?php echo htmlspecialchars($production['quantity']); ?>
                                        <?php if (!empty($production['unit'])): ?>
                                            <?php echo htmlspecialchars($production['unit']); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not specified</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Total Used:</strong></td>
                                <td>
                                    <?php if (!empty($production['total_used'])): ?>
                                        <span class="text-warning"><?php echo $production['total_used']; ?></span>
                                        <?php if (!empty($production['unit'])): ?>
                                            <?php echo htmlspecialchars($production['unit']); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-success">Not used yet</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Available:</strong></td>
                                <td>
                                    <?php if (!empty($production['quantity'])): ?>
                                        <?php 
                                        $available = $production['quantity'] - ($production['total_used'] ?? 0);
                                        $class = $available > 0 ? 'text-success' : 'text-danger';
                                        ?>
                                        <span class="<?php echo $class; ?> fw-bold">
                                            <?php echo $available; ?>
                                            <?php if (!empty($production['unit'])): ?>
                                                <?php echo htmlspecialchars($production['unit']); ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Not specified</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="info-card">
                        <h6><i class="fas fa-user me-2 text-primary"></i>Record Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Created By:</strong></td>
                                <td><?php echo htmlspecialchars($production['created_by_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Created On:</strong></td>
                                <td><?php echo date('F j, Y H:i', strtotime($production['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Last Updated:</strong></td>
                                <td><?php echo date('F j, Y H:i', strtotime($production['updated_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($production['short_note'])): ?>
            <div class="info-card mb-4">
                <h6><i class="fas fa-sticky-note me-2 text-primary"></i>Notes</h6>
                <p><?php echo nl2br(htmlspecialchars($production['short_note'])); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Uses History -->
            <div class="info-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0"><i class="fas fa-history me-2 text-primary"></i>Usage History</h6>
                    <button class="btn btn-sm btn-primary add-use-btn" data-id="<?php echo $production['id']; ?>">
                        <i class="fas fa-plus me-1"></i>Add Use
                    </button>
                </div>
                
                <?php if ($uses_result && mysqli_num_rows($uses_result) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Quantity</th>
                                    <th>Used By</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($use = mysqli_fetch_assoc($uses_result)): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($use['use_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($use['use_description']); ?></td>
                                    <td><span class="badge bg-warning"><?php echo $use['used_quantity']; ?></span></td>
                                    <td><?php echo htmlspecialchars($use['used_by']); ?></td>
                                    <td><?php echo htmlspecialchars($use['notes']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-3">No usage records found.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #3B9DB3;
            margin-bottom: 15px;
        }
        
        .info-card h6 {
            color: #333;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .table-sm td {
            padding: 8px 5px;
        }
        </style>
        
        <script>
        document.querySelector('.add-use-btn').addEventListener('click', function() {
            const prodId = this.getAttribute('data-id');
            // You can implement AJAX to show add use modal
            Swal.fire({
                title: 'Add Use',
                text: 'This would open the add use modal',
                icon: 'info'
            });
        });
        </script>
        
        <?php
    } else {
        echo '<div class="alert alert-danger">Production not found.</div>';
    }
} else {
    echo '<div class="alert alert-warning">Invalid request.</div>';
}
?>