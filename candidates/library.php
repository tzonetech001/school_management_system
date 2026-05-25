<?php
// candidates/library.php
session_start();
require_once '../controller/db_connect.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Get student information - FIXED: Get individual fields instead of full_name
$student_sql = "SELECT first_name, last_name, index_number, class, combination 
                FROM students WHERE id = $student_id";
$student_result = mysqli_query($conn, $student_sql);
$student = mysqli_fetch_assoc($student_result);
$student_name = $student['first_name'] . ' ' . $student['last_name'];

// Get all books for this student
$books_sql = "SELECT * FROM library_assignments 
              WHERE user_type = 'student' AND user_id = $student_id 
              ORDER BY 
                  CASE WHEN status = 'borrowed' THEN 0 ELSE 1 END,
                  assigned_date DESC";
$books_result = mysqli_query($conn, $books_sql);
$student_books = [];
if ($books_result && mysqli_num_rows($books_result) > 0) {
    while ($row = mysqli_fetch_assoc($books_result)) {
        $student_books[] = $row;
    }
}

// Separate borrowed and returned books
$borrowed_books = array_filter($student_books, function($book) {
    return $book['status'] == 'borrowed';
});

$returned_books = array_filter($student_books, function($book) {
    return $book['status'] == 'returned';
});

// Get book summary
function getStudentBookSummary($conn, $student_id) {
    $student_id = (int)$student_id;
    
    $sql = "SELECT 
                COUNT(*) as total_books,
                SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as borrowed_count,
                SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned_count,
                MIN(assigned_date) as first_assignment,
                MAX(assigned_date) as last_assignment,
                MAX(return_date) as last_return_date
            FROM library_assignments 
            WHERE user_type = 'student' AND user_id = $student_id";
    
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $summary = mysqli_fetch_assoc($result);
        // Ensure values are not null
        $summary['total_books'] = $summary['total_books'] ?? 0;
        $summary['borrowed_count'] = $summary['borrowed_count'] ?? 0;
        $summary['returned_count'] = $summary['returned_count'] ?? 0;
        return $summary;
    }
    
    return [
        'total_books' => 0,
        'borrowed_count' => 0,
        'returned_count' => 0,
        'first_assignment' => null,
        'last_assignment' => null,
        'last_return_date' => null
    ];
}

$summary = getStudentBookSummary($conn, $student_id);

// Get currently borrowed books
$current_borrowed_sql = "SELECT * FROM library_assignments 
                         WHERE user_type = 'student' AND user_id = $student_id 
                         AND status = 'borrowed'
                         ORDER BY assigned_date DESC";
$current_borrowed_result = mysqli_query($conn, $current_borrowed_sql);
$current_borrowed = [];
if ($current_borrowed_result && mysqli_num_rows($current_borrowed_result) > 0) {
    while ($row = mysqli_fetch_assoc($current_borrowed_result)) {
        $current_borrowed[] = $row;
    }
}

// Calculate days borrowed for current books
function getDaysBorrowed($assigned_date) {
    $assigned = new DateTime($assigned_date);
    $now = new DateTime();
    $interval = $assigned->diff($now);
    return $interval->days;
}

// Check if any books are overdue (more than 30 days)
function isOverdue($assigned_date) {
    return getDaysBorrowed($assigned_date) > 30;
}

// Get total overdue count
$overdue_count = 0;
foreach ($current_borrowed as $book) {
    if (isOverdue($book['assigned_date'])) {
        $overdue_count++;
    }
}
?>

<?php include 'header.php'; ?>
<?php include 'sidebar_student.php'; ?>

<!-- SweetAlert2 CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header - FIXED: Using student_name from first_name + last_name -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">
                <i class="fas fa-book-reader me-2" style="color: #3B9DB3;"></i>
                My Library Account
            </h2>
            <span class="badge bg-info fs-6 p-3">
                <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($student_name); ?>
            </span>
        </div>

        <!-- Student Info Card - FIXED: Following maintenance.php pattern -->
        <div class="card mb-4">
            <div class="card-body" style="background: white;">
                <div class="row">
                    <div class="col-md-4 col-sm-6 mb-2 mb-md-0">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle me-3">
                                <i class="fas fa-user-graduate text-primary fa-2x"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Student Name</div>
                                <strong><?php echo htmlspecialchars($student_name); ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6 mb-2 mb-md-0">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-id-card fa-2x text-primary me-3"></i>
                            <div>
                                <div class="text-muted small">Index Number</div>
                                <strong><?php echo htmlspecialchars($student['index_number']); ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-graduation-cap fa-2x text-success me-3"></i>
                            <div>
                                <div class="text-muted small">Class / Combination</div>
                                <strong><?php echo htmlspecialchars($student['class'] . ' ' . $student['combination']); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards - Following maintenance.php style -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-book" style="color: #3B9DB3;"></i>
                    </div>
                    <h3><?php echo $summary['total_books']; ?></h3>
                    <p>Total Books</p>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-hand-holding" style="color: #f093fb;"></i>
                    </div>
                    <h3><?php echo $summary['borrowed_count']; ?></h3>
                    <p>Currently Borrowed</p>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-check-circle" style="color: #4facfe;"></i>
                    </div>
                    <h3><?php echo $summary['returned_count']; ?></h3>
                    <p>Returned Books</p>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon">
                        <i class="fas fa-exclamation-triangle" style="color: #f5576c;"></i>
                    </div>
                    <h3><?php echo $overdue_count; ?></h3>
                    <p>Overdue Books</p>
                </div>
            </div>
        </div>

        <!-- Quick Status Alerts -->
        <?php if ($overdue_count > 0): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Attention!</strong> You have <?php echo $overdue_count; ?> overdue book(s). Please return them as soon as possible.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (empty($student_books)): ?>
        <!-- No Books State - Following maintenance.php empty state pattern -->
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <i class="fas fa-book-open fa-4x text-muted mb-3"></i>
                    <h5>No Books Found</h5>
                    <p class="text-muted">You haven't borrowed any books from the library yet.</p>
                    
                    <!-- Library Info - Following maintenance.php style -->
                    <div class="row justify-content-center mt-4">
                        <div class="col-md-8">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>Library Information</h6>
                                    <div class="row">
                                        <div class="col-sm-6 mb-2">
                                            <i class="fas fa-clock me-2 text-primary"></i>
                                            <span class="text-muted">Hours:</span> 8:00 AM - 5:00 PM
                                        </div>
                                        <div class="col-sm-6 mb-2">
                                            <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                            <span class="text-muted">Location:</span> Main Building, 2nd Floor
                                        </div>
                                        <div class="col-sm-6">
                                            <i class="fas fa-phone me-2 text-primary"></i>
                                            <span class="text-muted">Contact:</span> Ext. 123
                                        </div>
                                        <div class="col-sm-6">
                                            <i class="fas fa-envelope me-2 text-primary"></i>
                                            <span class="text-muted">Email:</span> library@muyovozi.ac.tz
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>

        <!-- Currently Borrowed Books Section -->
        <?php if (!empty($current_borrowed)): ?>
        <div class="card mb-4">
            <div class="card-header" style="background-color: #3B9DB3; color: white;">
                <h4 class="mb-0">
                    <i class="fas fa-hand-holding me-2"></i>
                    Currently Borrowed Books
                    <span class="badge bg-light text-dark ms-2"><?php echo count($current_borrowed); ?> Items</span>
                </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Book Title</th>
                                <th>Book Number</th>
                                <th>Quantity</th>
                                <th>Borrowed Date</th>
                                <th>Days Borrowed</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($current_borrowed as $book): 
                                $days = getDaysBorrowed($book['assigned_date']);
                                $overdue = isOverdue($book['assigned_date']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($book['book_title']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($book['book_number']); ?></td>
                                <td><?php echo htmlspecialchars($book['quantity']); ?></td>
                                <td><?php echo date('d M Y', strtotime($book['assigned_date'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $overdue ? 'bg-danger' : 'bg-info'; ?>">
                                        <?php echo $days; ?> days
                                    </span>
                                </td>
                                <td>
                                    <?php if ($overdue): ?>
                                        <span class="badge bg-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">On Time</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($book['short_note'])): ?>
                                        <i class="fas fa-sticky-note text-muted" 
                                           title="<?php echo htmlspecialchars($book['short_note']); ?>"></i>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Reading History Section -->
        <?php if (!empty($returned_books)): ?>
        <div class="card">
            <div class="card-header" style="background-color: #3B9DB3; color: white;">
                <h4 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Reading History
                    <span class="badge bg-light text-dark ms-2"><?php echo count($returned_books); ?> Books</span>
                </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Book Title</th>
                                <th>Book Number</th>
                                <th>Quantity</th>
                                <th>Borrowed Date</th>
                                <th>Returned Date</th>
                                <th>Duration</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($returned_books as $book): 
                                $borrowed = new DateTime($book['assigned_date']);
                                $returned = new DateTime($book['return_date']);
                                $duration = $borrowed->diff($returned)->days;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($book['book_title']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($book['book_number']); ?></td>
                                <td><?php echo htmlspecialchars($book['quantity']); ?></td>
                                <td><?php echo date('d M Y', strtotime($book['assigned_date'])); ?></td>
                                <td><?php echo date('d M Y', strtotime($book['return_date'])); ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $duration; ?> days</span>
                                </td>
                                <td>
                                    <?php if (!empty($book['short_note'])): ?>
                                        <small><?php echo htmlspecialchars($book['short_note']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Library Usage Summary - Following maintenance.php style -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header" style="background-color: #3B9DB3; color: white;">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Library Usage Summary</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-3 d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-calendar-plus me-2 text-primary"></i>First Borrowed Date</span>
                                <span class="badge bg-primary rounded-pill p-2">
                                    <?php echo $summary['first_assignment'] ? date('d M Y', strtotime($summary['first_assignment'])) : 'N/A'; ?>
                                </span>
                            </li>
                            <li class="mb-3 d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-calendar-check me-2 text-success"></i>Most Recent Borrow</span>
                                <span class="badge bg-success rounded-pill p-2">
                                    <?php echo $summary['last_assignment'] ? date('d M Y', strtotime($summary['last_assignment'])) : 'N/A'; ?>
                                </span>
                            </li>
                            <li class="mb-3 d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-undo-alt me-2 text-warning"></i>Last Return Date</span>
                                <span class="badge bg-warning rounded-pill p-2">
                                    <?php echo $summary['last_return_date'] ? date('d M Y', strtotime($summary['last_return_date'])) : 'N/A'; ?>
                                </span>
                            </li>
                            <li class="mb-3 d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-book-reader me-2 text-info"></i>Total Books Read</span>
                                <span class="badge bg-info rounded-pill p-2"><?php echo $summary['returned_count']; ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header" style="background-color: #3B9DB3; color: white;">
                        <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Library Guidelines</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Books can be borrowed for up to 30 days
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Return books on time to avoid penalties
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Handle books with care - no writing or damage
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Lost books must be reported immediately
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Maximum of 3 books can be borrowed at once
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<style>
/* Following maintenance.php styles */
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

.stats-card.simple-card .stats-icon {
    margin-bottom: 10px;
}

.stats-card.simple-card .stats-icon i {
    font-size: 2.2rem;
}

.stats-card.simple-card h3 {
    font-size: 1.8rem;
    font-weight: bold;
    color: #333;
    margin: 10px 0;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

.stats-card.simple-card p {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
    font-weight: 500;
}

.avatar-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: rgba(59, 157, 179, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state i {
    opacity: 0.5;
}

.empty-state h5 {
    color: #666;
    margin: 15px 0 10px 0;
}

.empty-state p {
    color: #999;
    margin-bottom: 20px;
}

.table th {
    font-weight: 600;
    color: #333;
    background-color: rgba(59, 157, 179, 0.05);
    border-bottom: 2px solid rgba(59, 157, 179, 0.2);
}

.badge {
    padding: 6px 10px;
    font-weight: 500;
    letter-spacing: 0.3px;
}

@media (max-width: 768px) {
    .stats-card.simple-card {
        padding: 15px;
    }
    
    .stats-card.simple-card h3 {
        font-size: 1.5rem;
    }
    
    .avatar-circle {
        width: 40px;
        height: 40px;
    }
}
</style>

<?php include '../controller/footer.php'; ?>