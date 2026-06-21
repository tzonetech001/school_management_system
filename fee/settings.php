<?php
// fee/settings.php
session_start();
require_once '../controller/db_connect.php';

// Check login & role
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Get admin info & school_id
$admin_sql = "SELECT a.*, s.school_name 
              FROM admins a 
              JOIN schools s ON a.school_id = s.id 
              WHERE a.id = ?";
$stmt = mysqli_prepare($conn, $admin_sql);
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);
$admin_result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($admin_result);

if (!$admin) {
    header("Location: ../index.php");
    exit();
}

$school_id = $admin['school_id'];

// Check if user is Head Master (role_id = 1)
$role_sql = "SELECT COUNT(*) as count 
             FROM admin_role_assignments ara 
             JOIN admin_roles ar ON ara.role_id = ar.id 
             WHERE ara.admin_id = ? AND ar.id = 1";
$role_stmt = mysqli_prepare($conn, $role_sql);
mysqli_stmt_bind_param($role_stmt, "i", $admin_id);
mysqli_stmt_execute($role_stmt);
$role_result = mysqli_stmt_get_result($role_stmt);
$role_count = mysqli_fetch_assoc($role_result)['count'];

if ($role_count == 0) {
    header("Location: ../dashboard.php?error=unauthorized");
    exit();
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $academic_year = mysqli_real_escape_string($conn, $_POST['academic_year']);
    $total_fee = floatval($_POST['total_fee']);
    $term_1 = floatval($_POST['term_1']);
    $term_2 = floatval($_POST['term_2']);
    // term_3 is set to 0 (we only use two terms)
    $term_3 = 0;

    // Upsert (insert or update)
    $insert_sql = "INSERT INTO fee_settings (school_id, academic_year, total_fee, term_1, term_2, term_3, updated_by)
                   VALUES (?, ?, ?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE 
                   total_fee = VALUES(total_fee), 
                   term_1 = VALUES(term_1), 
                   term_2 = VALUES(term_2), 
                   term_3 = VALUES(term_3),
                   updated_by = VALUES(updated_by)";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($insert_stmt, "isddddi", $school_id, $academic_year, $total_fee, $term_1, $term_2, $term_3, $admin_id);
    
    if (mysqli_stmt_execute($insert_stmt)) {
        $message = "Fee settings saved successfully!";
        $message_type = "success";
    } else {
        $message = "Error saving settings: " . mysqli_error($conn);
        $message_type = "danger";
    }
    mysqli_stmt_close($insert_stmt);
}

// Fetch current settings
$settings = null;
$settings_sql = "SELECT * FROM fee_settings WHERE school_id = ? ORDER BY updated_at DESC LIMIT 1";
$settings_stmt = mysqli_prepare($conn, $settings_sql);
mysqli_stmt_bind_param($settings_stmt, "i", $school_id);
mysqli_stmt_execute($settings_stmt);
$settings_result = mysqli_stmt_get_result($settings_stmt);
$settings = mysqli_fetch_assoc($settings_result);
mysqli_stmt_close($settings_stmt);

// Defaults if no settings exist
$current_year = date('Y') . '/' . (date('Y') + 1);

include '../controller/header.php';
include '../controller/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-coins me-2" style="color: var(--primary-color);"></i>Fee Settings</h2>
            <span class="badge bg-primary p-2"><?php echo htmlspecialchars($admin['school_name']); ?></span>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Set School Fee Structure (Two Terms)</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="academic_year" class="form-label fw-bold">Academic Year</label>
                                <input type="text" class="form-control" id="academic_year" name="academic_year" 
                                       value="<?php echo htmlspecialchars($settings['academic_year'] ?? $current_year); ?>" required>
                                <small class="text-muted">Format: 2025/2026</small>
                            </div>

                            <div class="mb-3">
                                <label for="total_fee" class="form-label fw-bold">Total Annual Fee (TZS)</label>
                                <input type="number" step="0.01" class="form-control" id="total_fee" name="total_fee" 
                                       value="<?php echo htmlspecialchars($settings['total_fee'] ?? 0); ?>" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="term_1" class="form-label">Term 1 Fee (TZS)</label>
                                    <input type="number" step="0.01" class="form-control" id="term_1" name="term_1" 
                                           value="<?php echo htmlspecialchars($settings['term_1'] ?? 0); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="term_2" class="form-label">Term 2 Fee (TZS)</label>
                                    <input type="number" step="0.01" class="form-control" id="term_2" name="term_2" 
                                           value="<?php echo htmlspecialchars($settings['term_2'] ?? 0); ?>">
                                </div>
                            </div>
                            <p class="text-muted small">Note: Only two terms are used for this school.</p>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="fas fa-save me-2"></i>Save Settings
                                </button>
                            </div>
                        </form>

                        <?php if ($settings): ?>
                            <hr>
                            <div class="mt-3">
                                <p class="text-muted mb-0">
                                    <i class="far fa-clock me-1"></i> Last updated: 
                                    <?php echo date('F j, Y g:i A', strtotime($settings['updated_at'])); ?>
                                    by Admin ID: <?php echo $settings['updated_by']; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../controller/footer.php'; ?>