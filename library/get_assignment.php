<?php
session_start();
require_once '../controller/db_connect.php';

header('Content-Type: text/html; charset=UTF-8');

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Get the assignment
    $sql = "SELECT * FROM library_assignments WHERE id = $id";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $assignment = mysqli_fetch_assoc($result);
        
        // Get user details with proper full_name handling
        $user_sql = "";
        if ($assignment['user_type'] == 'staff') {
            $user_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, 
                                phone_number, email, status 
                         FROM admins WHERE id = " . (int)$assignment['user_id'];
        } else {
            $user_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, 
                                index_number, combination, status,
                                NULL as phone_number, NULL as email
                         FROM students WHERE id = " . (int)$assignment['user_id'];
        }
        
        $user_result = mysqli_query($conn, $user_sql);
        $user_details = ($user_result && mysqli_num_rows($user_result) > 0) ? mysqli_fetch_assoc($user_result) : [];
        
        // Get all books for this user
        $all_books_sql = "SELECT * FROM library_assignments 
                         WHERE user_type = '{$assignment['user_type']}' 
                         AND user_id = {$assignment['user_id']} 
                         ORDER BY 
                            CASE WHEN status = 'borrowed' THEN 0 ELSE 1 END,
                            assigned_date DESC";
        $all_books_result = mysqli_query($conn, $all_books_sql);
        $all_books = [];
        $borrowed_count = 0;
        if ($all_books_result && mysqli_num_rows($all_books_result) > 0) {
            while ($book = mysqli_fetch_assoc($all_books_result)) {
                $all_books[] = $book;
                if ($book['status'] == 'borrowed') {
                    $borrowed_count++;
                }
            }
        }
        ?>
        <div class="container-fluid">
            <!-- Current Assignment -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-info text-white py-3">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Current Assignment</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center mb-3 mb-md-0">
                                    <div class="avatar-circle mx-auto" 
                                         style="width: 100px; height: 100px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                                border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-book fa-3x text-white"></i>
                                    </div>
                                    <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($assignment['book_title']); ?></h5>
                                    <p class="text-muted mb-0">#<?php echo htmlspecialchars($assignment['book_number']); ?></p>
                                </div>
                                <div class="col-md-9">
                                    <div class="row">
                                        <div class="col-sm-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="icon-circle bg-light rounded-circle p-3 me-3">
                                                    <i class="fas fa-user <?php echo $assignment['user_type'] == 'staff' ? 'text-info' : 'text-success'; ?>"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">User Type</small>
                                                    <strong>
                                                        <span class="badge <?php echo $assignment['user_type'] == 'staff' ? 'bg-info' : 'bg-success'; ?> p-2">
                                                            <i class="fas <?php echo $assignment['user_type'] == 'staff' ? 'fa-chalkboard-teacher' : 'fa-user-graduate'; ?> me-1"></i>
                                                            <?php echo ucfirst($assignment['user_type']); ?>
                                                        </span>
                                                        <?php if (isset($user_details['status']) && $user_details['status'] == 0): ?>
                                                            <span class="badge bg-danger ms-2">Inactive</span>
                                                        <?php endif; ?>
                                                    </strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="icon-circle bg-light rounded-circle p-3 me-3">
                                                    <i class="fas fa-id-card text-primary"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">User Name</small>
                                                    <strong><?php echo htmlspecialchars($assignment['user_name']); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($assignment['user_type'] == 'staff'): ?>
                                            <?php if (!empty($user_details['phone_number'])): ?>
                                            <div class="col-sm-6 mb-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="icon-circle bg-light rounded-circle p-3 me-3">
                                                        <i class="fas fa-phone text-success"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block">Phone Number</small>
                                                        <a href="tel:<?php echo htmlspecialchars($user_details['phone_number']); ?>" class="text-decoration-none">
                                                            <strong><?php echo htmlspecialchars($user_details['phone_number']); ?></strong>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($user_details['email'])): ?>
                                            <div class="col-sm-6 mb-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="icon-circle bg-light rounded-circle p-3 me-3">
                                                        <i class="fas fa-envelope text-info"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block">Email</small>
                                                        <a href="mailto:<?php echo htmlspecialchars($user_details['email']); ?>" class="text-decoration-none">
                                                            <strong><?php echo htmlspecialchars($user_details['email']); ?></strong>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if (!empty($user_details['index_number'])): ?>
                                            <div class="col-sm-6 mb-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="icon-circle bg-light rounded-circle p-3 me-3">
                                                        <i class="fas fa-hashtag text-warning"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block">Index Number</small>
                                                        <strong><?php echo htmlspecialchars($user_details['index_number']); ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($user_details['combination'])): ?>
                                            <div class="col-sm-6 mb-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="icon-circle bg-light rounded-circle p-3 me-3">
                                                        <i class="fas fa-layer-group text-primary"></i>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted d-block">Combination</small>
                                                        <strong><?php echo htmlspecialchars($user_details['combination']); ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <div class="col-sm-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="icon-circle bg-light rounded-circle p-3 me-3">
                                                    <i class="fas fa-cubes text-secondary"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Quantity</small>
                                                    <strong><?php echo htmlspecialchars($assignment['quantity']); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="icon-circle bg-light rounded-circle p-3 me-3">
                                                    <i class="fas fa-clock <?php echo $assignment['status'] == 'borrowed' ? 'text-warning' : 'text-success'; ?>"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Status</small>
                                                    <span class="badge <?php echo $assignment['status'] == 'borrowed' ? 'bg-warning' : 'bg-success'; ?> p-2">
                                                        <i class="fas <?php echo $assignment['status'] == 'borrowed' ? 'fa-hourglass-half' : 'fa-check-circle'; ?> me-1"></i>
                                                        <?php echo ucfirst($assignment['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="icon-circle bg-light rounded-circle p-3 me-3">
                                                    <i class="fas fa-calendar-alt text-danger"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Assigned Date</small>
                                                    <strong><?php echo date('F j, Y', strtotime($assignment['assigned_date'])); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if ($assignment['status'] == 'returned' && !empty($assignment['return_date'])): ?>
                                        <div class="col-sm-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="icon-circle bg-light rounded-circle p-3 me-3">
                                                    <i class="fas fa-calendar-check text-success"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Return Date</small>
                                                    <strong><?php echo date('F j, Y', strtotime($assignment['return_date'])); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($assignment['short_note'])): ?>
                                    <div class="mt-3 p-3 bg-light rounded">
                                        <small class="text-muted d-block mb-2"><i class="fas fa-sticky-note me-2"></i>Short Note:</small>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($assignment['short_note'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- All Books Summary -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <h3 class="text-warning mb-2"><?php echo $borrowed_count; ?></h3>
                            <p class="text-muted mb-0">Active Borrowed Books</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <h3 class="text-success mb-2"><?php echo count($all_books) - $borrowed_count; ?></h3>
                            <p class="text-muted mb-0">Returned Books</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- All Books for this User -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-primary text-white py-3">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>All Books for <?php echo htmlspecialchars($assignment['user_name']); ?></h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="px-4">#</th>
                                            <th>Book Title</th>
                                            <th>Book No.</th>
                                            <th>Quantity</th>
                                            <th>Status</th>
                                            <th>Assigned</th>
                                            <th>Returned</th>
                                            <th class="px-4">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($all_books)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                <i class="fas fa-book-open fa-2x mb-2 d-block"></i>
                                                No other books found for this user.
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($all_books as $index => $book): ?>
                                            <tr class="<?php echo $book['id'] == $id ? 'table-info' : ''; ?>">
                                                <td class="px-4"><?php echo $index + 1; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($book['book_title']); ?></strong>
                                                    <?php if (!empty($book['short_note'])): ?>
                                                        <i class="fas fa-sticky-note text-muted ms-2" 
                                                           title="<?php echo htmlspecialchars($book['short_note']); ?>"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($book['book_number']); ?></td>
                                                <td><?php echo htmlspecialchars($book['quantity']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $book['status'] == 'borrowed' ? 'bg-warning' : 'bg-success'; ?>">
                                                        <?php echo ucfirst($book['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($book['assigned_date'])); ?></td>
                                                <td><?php echo $book['return_date'] ? date('d/m/Y', strtotime($book['return_date'])) : '-'; ?></td>
                                                <td class="px-4">
                                                    <a href="book_details.php?user_type=<?php echo $assignment['user_type']; ?>&user_id=<?php echo $assignment['user_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="View All">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-white py-3">
                            <a href="book_details.php?user_type=<?php echo $assignment['user_type']; ?>&user_id=<?php echo $assignment['user_id']; ?>" 
                               class="btn btn-primary">
                                <i class="fas fa-arrow-right me-2"></i>View Complete History
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    } else {
        ?>
        <div class="text-center py-5">
            <i class="fas fa-exclamation-circle fa-4x text-danger mb-3"></i>
            <h4 class="text-danger">Assignment Not Found</h4>
            <p class="text-muted">The requested book assignment does not exist or has been deleted.</p>
        </div>
        <?php
    }
} else {
    ?>
    <div class="text-center py-5">
        <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
        <h4 class="text-warning">Invalid Request</h4>
        <p class="text-muted">Please provide a valid assignment ID.</p>
    </div>
    <?php
}
?>