<?php
require_once '../controller/db_connect.php';

$sql = "SELECT pc.*, 
        COUNT(p.id) as production_count,
        COALESCE(SUM(p.amount), 0) as total_value
        FROM production_categories pc
        LEFT JOIN productions p ON pc.category_name = p.category
        WHERE pc.status = 1
        GROUP BY pc.id
        ORDER BY pc.category_name";
        
$result = mysqli_query($conn, $sql);

if (!$result) {
    echo '<div class="alert alert-danger">Error loading categories: ' . mysqli_error($conn) . '</div>';
    exit();
}

if (mysqli_num_rows($result) > 0):
?>
    <div class="list-group">
        <?php while ($cat = mysqli_fetch_assoc($result)): ?>
        <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <strong><?php echo ucfirst(htmlspecialchars($cat['category_name'])); ?></strong>
                <?php if (!empty($cat['unit'])): ?>
                    <small class="text-muted">(Unit: <?php echo htmlspecialchars($cat['unit']); ?>)</small>
                <?php endif; ?>
                <?php if (!empty($cat['description'])): ?>
                    <div class="small text-muted"><?php echo htmlspecialchars($cat['description']); ?></div>
                <?php endif; ?>
            </div>
            <div>
                <span class="badge bg-primary rounded-pill"><?php echo $cat['production_count']; ?></span>
                <?php if ($cat['production_count'] > 0): ?>
                    <small class="text-success ms-2">
                        <i class="fas fa-money-bill-wave"></i> 
                        <?php echo number_format($cat['total_value'], 2); ?> TZS
                    </small>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-danger ms-2" 
                        onclick="deleteCategory(<?php echo $cat['id']; ?>)"
                        title="Delete Category">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <p class="text-muted">No categories found. Add your first category!</p>
<?php endif; ?>

<script>
function deleteCategory(id) {
    Swal.fire({
        title: 'Delete Category?',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('delete_category.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        loadCategories();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message,
                        icon: 'error',
                        confirmButtonColor: '#d33'
                    });
                }
            });
        }
    });
}
</script>