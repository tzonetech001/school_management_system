<?php
// teacher_timetable.php - Teachers view with proper permissions
session_start();
require_once '../controller/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../mhs/login.php');
    exit();
}

$user_id = intval($_SESSION['admin_id']);

$school_query = "SELECT school_id FROM admins WHERE id = $user_id";
$school_result = mysqli_query($conn, $school_query);
$school_data = mysqli_fetch_assoc($school_result);
$school_id = $school_data['school_id'];

$user_query = "SELECT first_name, last_name FROM admins WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);
$user_name = $user['first_name'] . ' ' . $user['last_name'];

// Check if user has admin role (Academic Master, Head Master, Second Master)
$has_admin_role = false;
$admin_roles_query = "SELECT ar.role_name FROM admin_role_assignments ara 
                      JOIN admin_roles ar ON ara.role_id = ar.id 
                      WHERE ara.admin_id = $user_id 
                      AND ar.role_name IN ('Head Master', 'Second Master', 'Academic Master')";
$admin_roles_result = mysqli_query($conn, $admin_roles_query);
if ($admin_roles_result && mysqli_num_rows($admin_roles_result) > 0) {
    $has_admin_role = true;
}

// Get user's role names for display
$user_roles_query = "SELECT ar.role_name FROM admin_role_assignments ara 
                      JOIN admin_roles ar ON ara.role_id = ar.id 
                      WHERE ara.admin_id = $user_id";
$user_roles_result = mysqli_query($conn, $user_roles_query);
$user_roles = [];
while ($row = mysqli_fetch_assoc($user_roles_result)) {
    $user_roles[] = $row['role_name'];
}
$role_display = !empty($user_roles) ? implode(', ', $user_roles) : 'Teacher';

// Handle delete - CHECK PERMISSION
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $timetable_id = intval($_GET['id']);
    
    // Check if user has permission to delete
    $check_sql = "SELECT filename, generated_by FROM generated_timetables WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $timetable_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $timetable = $check_result->fetch_assoc();
        
        if ($has_admin_role || $timetable['generated_by'] == $user_id) {
            // Delete the physical file
            $file_path = '../uploads/timetables/' . $timetable['filename'] . '.html';
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Delete from database
            $delete_sql = "DELETE FROM generated_timetables WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $timetable_id);
            $delete_stmt->execute();
            
            $_SESSION['success'] = "Timetable deleted successfully!";
        } else {
            $_SESSION['error'] = "You don't have permission to delete this timetable.";
        }
    } else {
        $_SESSION['error'] = "Timetable not found.";
    }
    
    header("Location: teacher_timetable.php");
    exit();
}

// Handle edit - redirect to edit page
if (isset($_GET['edit']) && isset($_GET['id'])) {
    $timetable_id = intval($_GET['id']);
    
    // Check permission
    $check_sql = "SELECT filename, generated_by FROM generated_timetables WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $timetable_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $timetable = $check_result->fetch_assoc();
        
        if ($has_admin_role || $timetable['generated_by'] == $user_id) {
            header("Location: edit_timetable.php?id=" . $timetable_id);
            exit();
        } else {
            $_SESSION['error'] = "You don't have permission to edit this timetable.";
            header("Location: timetable.php");
            exit();
        }
    }
}

// Get timetables
$timetables_query = "SELECT gt.*, 
       CONCAT(gf.first_name, ' ', gf.last_name) AS generated_by_name, 
       CONCAT(uf.first_name, ' ', uf.last_name) AS updated_by_name 
       FROM generated_timetables gt 
       LEFT JOIN admins gf ON gt.generated_by = gf.id 
       LEFT JOIN admins uf ON gt.last_updated_by = uf.id 
       WHERE gt.school_id = $school_id 
       ORDER BY gt.year DESC, FIELD(gt.term, 'Term 02', 'Term 01')";
$timetables_result = mysqli_query($conn, $timetables_query);
$available_timetables = [];
while ($row = mysqli_fetch_assoc($timetables_result)) {
    $available_timetables[] = $row;
}

// Theme settings
$theme_settings = [];
$settings_query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $user_id";
$settings_result = mysqli_query($conn, $settings_query);
while ($row = mysqli_fetch_assoc($settings_result)) {
    $theme_settings[$row['setting_key']] = $row['setting_value'];
}

$preferences = [];
$prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = $user_id";
$prefs_result = mysqli_query($conn, $prefs_query);
while ($row = mysqli_fetch_assoc($prefs_result)) {
    $preferences[$row['preference_key']] = $row['preference_value'];
}

$default_colors = [
    'primary' => '#3B9DB3', 'primary_dark' => '#2d7c8f', 'primary_light' => '#8bc5d6',
    'light' => '#f8f9fa', 'white' => '#ffffff', 'gray' => '#e9ecef',
    'text' => '#333333', 'text_light' => '#666666', 'border' => '#e0e0e0'
];

$colors = $default_colors;
foreach ($theme_settings as $key => $value) {
    if (array_key_exists($key, $colors)) {
        $colors[$key] = $value;
    }
}

$font_sizes = ['10' => '10px', '12' => '12px', '14' => '14px', '16' => '16px', '18' => '18px'];
$font_size = isset($preferences['font_size']) ? $font_sizes[$preferences['font_size']] : '16px';
$compact_mode = isset($preferences['compact_mode']) && $preferences['compact_mode'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: <?php echo $colors['primary']; ?>;
            --primary-dark: <?php echo $colors['primary_dark']; ?>;
            --primary-light: <?php echo $colors['primary_light']; ?>;
            --font-size-base: <?php echo $font_size; ?>;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; font-size: var(--font-size-base); }
        .main-content { margin-left: 260px; padding: 20px; min-height: 100vh; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } }
        
        .welcome-card { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); border-radius: 15px; padding: 25px; color: white; margin-bottom: 25px; }
        .timetable-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); transition: all 0.3s ease; height: 100%; }
        .timetable-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        .timetable-card-header { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; padding: 15px; text-align: center; }
        .timetable-card-body { padding: 20px; text-align: center; }
        .timetable-icon { font-size: 48px; color: var(--primary-color); margin-bottom: 15px; }
        
        .btn-download { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; border: none; padding: 8px 16px; border-radius: 6px; margin: 3px; cursor: pointer; }
        .btn-download:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(59, 157, 179, 0.3); }
        .btn-view { background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 6px; margin: 3px; cursor: pointer; }
        .btn-view:hover { background: #5a6268; transform: translateY(-2px); }
        .btn-delete { background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 6px; margin: 3px; cursor: pointer; }
        .btn-delete:hover { background: #c82333; transform: translateY(-2px); }
        .btn-edit { background: #ffc107; color: #333; border: none; padding: 8px 16px; border-radius: 6px; margin: 3px; cursor: pointer; }
        .btn-edit:hover { background: #e0a800; transform: translateY(-2px); }
        
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 12px; }
        .empty-icon { font-size: 80px; color: var(--primary-light); margin-bottom: 20px; }
        .filter-section { background: white; border-radius: 12px; padding: 15px; margin-bottom: 25px; }
        
        .role-badge { padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .admin-badge { background: #ffc107; color: #333; }
        .generator-badge { background: #17a2b8; color: white; }
        .teacher-badge { background: #6c757d; color: white; }
        
        .action-buttons { display: flex; flex-wrap: wrap; justify-content: center; gap: 5px; margin-top: 10px; }
        
        @media (max-width: 768px) {
            .action-buttons .btn { font-size: 11px; padding: 6px 12px; }
        }
    </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="welcome-card">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h2 class="mb-2">
                            <i class="fas fa-<?php echo $has_admin_role ? 'crown' : 'chalkboard-teacher'; ?> me-2"></i>
                            Timetable Management
                        </h2>
                        <p class="mb-0 opacity-75">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($user_name); ?>
                            <span class="role-badge <?php echo $has_admin_role ? 'admin-badge' : 'teacher-badge'; ?>">
                                <?php echo $has_admin_role ? 'Administrator' : htmlspecialchars($role_display); ?>
                            </span>
                        </p>
                    </div>
                    <div class="mt-3 mt-sm-0 text-end">
                        <?php if ($has_admin_role): ?>
                            <a href="session_timetable.php" class="btn btn-success btn-sm">
                                <i class="fas fa-plus-circle me-1"></i>Create New
                            </a>
                            <br>
                            <span class="badge bg-warning text-dark mt-2">
                                <i class="fas fa-crown me-1"></i>Full Access (All Timetables)
                            </span>
                        <?php else: ?>
                            <span class="badge bg-info text-white">
                                <i class="fas fa-user me-1"></i>My Timetables Only
                            </span>
                            <br>
                            <span class="badge bg-light text-dark mt-2">
                                <i class="fas fa-info-circle me-1"></i>You can edit/delete timetables you created
                            </span>
                        <?php endif; ?>
                        <div class="mt-2">
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-file-alt me-1"></i>
                                <?php echo count($available_timetables); ?> Timetable(s)
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="filter-section">
                <div class="row align-items-center">
                    <div class="col-md-4 mb-2 mb-md-0">
                        <label class="form-label"><i class="fas fa-filter me-1"></i>Term</label>
                        <select id="filterTerm" class="form-select">
                            <option value="all">All Terms</option>
                            <option value="Term 01">Term 01</option>
                            <option value="Term 02">Term 02</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2 mb-md-0">
                        <label class="form-label"><i class="fas fa-calendar me-1"></i>Year</label>
                        <select id="filterYear" class="form-select"><option value="all">All Years</option></select>
                    </div>
                    <div class="col-md-4">
                        <button id="resetFilters" class="btn btn-secondary w-100">
                            <i class="fas fa-undo-alt me-2"></i>Reset Filters
                        </button>
                    </div>
                </div>
            </div>

            <div class="row" id="timetablesGrid">
                <?php if (empty($available_timetables)): ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-calendar-times"></i></div>
                            <h4>No Timetables Available</h4>
                            <p class="text-muted">No timetables have been generated yet.</p>
                            <?php if ($has_admin_role): ?>
                                <a href="session_timetable.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i>Create New Timetable
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($available_timetables as $timetable): 
                        $is_generator = ($timetable['generated_by'] == $user_id);
                        $can_edit_delete = ($has_admin_role || $is_generator);
                        
                        if ($has_admin_role) {
                            $badge_text = 'Admin Access';
                            $badge_class = 'bg-warning text-dark';
                            $badge_icon = 'crown';
                        } elseif ($is_generator) {
                            $badge_text = 'You Created This';
                            $badge_class = 'bg-info text-white';
                            $badge_icon = 'user-edit';
                        } else {
                            $badge_text = 'View Only';
                            $badge_class = 'bg-secondary text-white';
                            $badge_icon = 'eye';
                        }
                    ?>
                        <div class="col-md-6 col-lg-4 mb-4 timetable-item" 
                             data-term="<?php echo htmlspecialchars($timetable['term']); ?>" 
                             data-year="<?php echo $timetable['year']; ?>">
                            <div class="timetable-card">
                                <div class="timetable-card-header">
                                    <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($timetable['term']); ?></h5>
                                    <small><?php echo $timetable['year']; ?></small>
                                    <?php if (!empty($timetable['generated_at'])): ?>
                                        <br>
                                        <small style="font-size:11px;color:#f1f1f1;opacity:0.95;">Generated: <?php echo date('l, F d, Y g:i A', strtotime($timetable['generated_at'])); ?></small>
                                    <?php endif; ?>
                                    <br>
                                    <small class="badge <?php echo $badge_class; ?> mt-1">
                                        <i class="fas fa-<?php echo $badge_icon; ?> me-1"></i>
                                        <?php echo $badge_text; ?>
                                    </small>
                                </div>
                                <div class="timetable-card-body">
                                    <div class="timetable-icon"><i class="fas fa-file-alt"></i></div>
                                    <h6><?php echo htmlspecialchars($timetable['document_name'] ?: ($timetable['term'] . ' Timetable - ' . $timetable['year'])); ?></h6>
                                    <p class="text-muted small">
                                        <?php echo $timetable['sessions_per_day'] ?? '6'; ?> sessions | 
                                        Break: <?php echo ($timetable['break_after'] ?? 0) > 0 ? 'After Session ' . ($timetable['break_after'] ?? 0) : 'No break'; ?>
                                        <?php if (!empty($timetable['days'])): ?>
                                            <br>Days: <?php echo htmlspecialchars($timetable['days']); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($timetable['updated_by_name'])): ?>
                                            <br>Updated by: <?php echo htmlspecialchars($timetable['updated_by_name']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <div class="action-buttons">
                                        <button class="btn-download" onclick="downloadTimetable('<?php echo htmlspecialchars($timetable['filename']); ?>')">
                                            <i class="fas fa-download me-1"></i>Download
                                        </button>
                                        <button class="btn-view" onclick="viewTimetable('<?php echo htmlspecialchars($timetable['filename']); ?>')">
                                            <i class="fas fa-eye me-1"></i>View
                                        </button>
                                        <?php if ($can_edit_delete): ?>
                                            <button class="btn-edit" onclick="editTimetable(<?php echo $timetable['id']; ?>)">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                            <button class="btn-delete" onclick="deleteTimetable(<?php echo $timetable['id']; ?>)">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Timetable Modal -->
    <div class="modal fade" id="viewTimetableModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                    <h5 class="modal-title"><i class="fas fa-calendar-alt me-2"></i><span id="modalTitle">Timetable</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" id="modalBody">
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-3">Loading...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="printModalBtn">
                        <i class="fas fa-print me-2"></i>Print / Save as PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include '../controller/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function filterTimetables() {
            const term = $('#filterTerm').val();
            const year = $('#filterYear').val();
            
            $('.timetable-item').each(function() {
                const itemTerm = $(this).data('term');
                const itemYear = $(this).data('year').toString();
                
                let show = true;
                if (term !== 'all' && itemTerm !== term) show = false;
                if (year !== 'all' && itemYear !== year) show = false;
                
                $(this).toggle(show);
            });
            
            if ($('.timetable-item:visible').length === 0 && $('.timetable-item').length > 0) {
                if ($('#noResultsMsg').length === 0) {
                    $('#timetablesGrid').append(`
                        <div id="noResultsMsg" class="col-12">
                            <div class="alert alert-warning text-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No timetables match your filters.
                            </div>
                        </div>
                    `);
                }
            } else {
                $('#noResultsMsg').remove();
            }
        }
        
        $('#filterTerm, #filterYear').on('change', filterTimetables);
        
        $('#resetFilters').on('click', function() {
            $('#filterTerm').val('all');
            $('#filterYear').val('all');
            filterTimetables();
        });
        
        function downloadTimetable(filename) {
            window.location.href = 'download_timetable.php?file=' + encodeURIComponent(filename + '.html');
        }
        
        function viewTimetable(filename) {
            const displayName = filename.replace(/_/g, ' ');
            $('#modalTitle').text(displayName);
            $('#modalBody').html(`
                <div class="text-center p-5">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-3">Loading timetable...</p>
                </div>
            `);
            
            $.ajax({
                url: 'view_timetable.php?file=' + encodeURIComponent(filename + '.html'),
                success: function(response) {
                    $('#modalBody').html(response);
                },
                error: function() {
                    $('#modalBody').html(`
                        <div class="alert alert-danger m-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading timetable. Please try again.
                        </div>
                    `);
                }
            });
            
            new bootstrap.Modal(document.getElementById('viewTimetableModal')).show();
        }
        
        function deleteTimetable(id) {
            Swal.fire({
                title: 'Delete Timetable?',
                text: 'This will permanently remove this timetable from the system. All teachers will lose access to it. This action cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it permanently!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show processing message
                    Swal.fire({
                        title: 'Deleting...',
                        text: 'Please wait while the timetable is being removed.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Redirect to delete
                    window.location.href = 'timetable.php?delete=1&id=' + id;
                }
            });
        }
        
        function editTimetable(id) {
            window.location.href = 'edit_timetable.php?id=' + id;
        }
        
        $('#printModalBtn').on('click', function() {
            const printContent = $('#modalBody').html();
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Timetable</title>
                        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                        <style>
                            body { padding: 20px; }
                            @media print { body { margin: 0; padding: 10px; } }
                        </style>
                    </head>
                    <body>
                        ${printContent}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        });
        
        const years = [...new Set($('.timetable-item').map(function() {
            return $(this).data('year');
        }).get())].sort().reverse();
        
        years.forEach(function(year) {
            $('#filterYear').append(`<option value="${year}">${year}</option>`);
        });
    </script>
</body>
</html>