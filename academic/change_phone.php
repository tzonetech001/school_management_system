<?php
// change_phone.php - Change Parent Phone Number via Popup Modal
session_start();
require_once '../controller/db_connect.php';

// Check login
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$current_year = date('Y');

// Check user permission
$user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}

$has_permission = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2 || $role_id == 3 || $role_id == 12) {
        $has_permission = true;
        break;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "You don't have permission to change parent phone numbers.";
    header("Location: ../404.php");
    exit();
}

// Load theme settings
$theme_settings = [];
$settings_query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id";
$settings_result = mysqli_query($conn, $settings_query);
if ($settings_result && mysqli_num_rows($settings_result) > 0) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $theme_settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Load user preferences
$preferences = [];
$prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = $admin_id";
$prefs_result = mysqli_query($conn, $prefs_query);
if ($prefs_result && mysqli_num_rows($prefs_result) > 0) {
    while ($row = mysqli_fetch_assoc($prefs_result)) {
        $preferences[$row['preference_key']] = $row['preference_value'];
    }
}

// Default colors
$default_colors = [
    'primary' => '#3B9DB3',
    'primary_dark' => '#2d7c8f',
    'primary_light' => '#8bc5d6',
    'light' => '#f8f9fa',
    'white' => '#ffffff',
    'gray' => '#e9ecef',
    'text' => '#333333',
    'text_light' => '#666666',
    'border' => '#e0e0e0',
    'success' => '#28a745',
    'danger' => '#dc3545',
    'warning' => '#ffc107',
    'info' => '#17a2b8'
];

$colors = $default_colors;
foreach ($theme_settings as $key => $value) {
    if (array_key_exists($key, $colors)) {
        $colors[$key] = $value;
    }
}

// Font size and compact mode
$font_sizes = ['10' => '10px', '12' => '12px', '14' => '14px', '16' => '16px', '18' => '18px'];
$font_size = isset($preferences['font_size']) ? $font_sizes[$preferences['font_size']] : '16px';
$compact_mode = isset($preferences['compact_mode']) && $preferences['compact_mode'] === '1';
$animations = isset($preferences['animations']) && $preferences['animations'] === '1';
$animation_speed = isset($preferences['animation_speed']) ? $preferences['animation_speed'] : 'normal';
$animation_time = $animation_speed === 'slow' ? '0.5s' : ($animation_speed === 'fast' ? '0.15s' : '0.3s');

// Get filters for student list
$selected_form = isset($_GET['form']) ? mysqli_real_escape_string($conn, $_GET['form']) : 'Form five';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$db_form = ($selected_form == 'Form five') ? 'Form five' : 'Form six';
$display_form = ($selected_form == 'Form five') ? 'Form Five' : 'Form Six';

// Get all students
$sql = "SELECT s.id, s.index_number, s.first_name, s.last_name, s.second_name, s.sex, s.combination, 
        s.parent_phone, s.parent_name
        FROM students s
        WHERE s.class = '$db_form' AND (s.is_leaver IS NULL OR s.is_leaver = FALSE)";

if ($search_query) {
    $sql .= " AND (s.index_number LIKE '%$search_query%' 
               OR s.first_name LIKE '%$search_query%' 
               OR s.last_name LIKE '%$search_query%')";
}

$sql .= " ORDER BY s.first_name, s.last_name";

$students_result = mysqli_query($conn, $sql);
$students = [];
while ($row = mysqli_fetch_assoc($students_result)) {
    $row['parent_phone_formatted'] = formatPhoneNumberDisplay($row['parent_phone']);
    $students[] = $row;
}

// Function to format phone number for display
function formatPhoneNumberDisplay($phone) {
    if (empty($phone)) return '';
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 12 && substr($phone, 0, 3) == '255') {
        return '0' . substr($phone, 3);
    }
    return $phone;
}

// Function to format phone number for database (with 255 prefix)
function formatPhoneNumberForDB($phone) {
    if (empty($phone)) return null;
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 3) === '255') {
        return $phone;
    }
    if (substr($phone, 0, 1) === '0') {
        return '255' . substr($phone, 1);
    }
    if (substr($phone, 0, 1) === '7' || substr($phone, 0, 1) === '6') {
        return '255' . $phone;
    }
    return $phone;
}

// Handle AJAX request to get student details
if (isset($_GET['get_student'])) {
    header('Content-Type: application/json');
    $student_id = intval($_GET['student_id']);
    
    $sql = "SELECT id, index_number, first_name, last_name, second_name, sex, combination, 
            parent_phone, parent_name, parent_occupation, parent_residence
            FROM students WHERE id = $student_id";
    $result = mysqli_query($conn, $sql);
    $student = mysqli_fetch_assoc($result);
    
    if ($student) {
        $student['parent_phone_formatted'] = formatPhoneNumberDisplay($student['parent_phone']);
        echo json_encode(['success' => true, 'student' => $student]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Student not found']);
    }
    exit();
}

// Handle AJAX request to update parent phone number
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_phone'])) {
    header('Content-Type: application/json');
    
    $student_id = intval($_POST['student_id']);
    $parent_phone = trim($_POST['parent_phone']);
    $parent_name = mysqli_real_escape_string($conn, trim($_POST['parent_name']));
    $parent_occupation = mysqli_real_escape_string($conn, trim($_POST['parent_occupation']));
    $parent_residence = mysqli_real_escape_string($conn, trim($_POST['parent_residence']));
    
    if (empty($student_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
        exit();
    }
    
    // Format phone number for database
    $formatted_phone = !empty($parent_phone) ? formatPhoneNumberForDB($parent_phone) : null;
    
    // Update student record
    $update_sql = "UPDATE students SET 
                   parent_phone = " . ($formatted_phone ? "'$formatted_phone'" : "NULL") . ",
                   parent_name = " . ($parent_name ? "'$parent_name'" : "NULL") . ",
                   parent_occupation = " . ($parent_occupation ? "'$parent_occupation'" : "NULL") . ",
                   parent_residence = " . ($parent_residence ? "'$parent_residence'" : "NULL") . "
                   WHERE id = $student_id";
    
    if (mysqli_query($conn, $update_sql)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Parent information updated successfully!',
            'display_phone' => formatPhoneNumberDisplay($formatted_phone)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Parent Phone Number</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $colors['primary']; ?>;
            --primary-dark: <?php echo $colors['primary_dark']; ?>;
            --primary-light: <?php echo $colors['primary_light']; ?>;
            --text-color: <?php echo $colors['text']; ?>;
            --text-light: <?php echo $colors['text_light']; ?>;
            --border-color: <?php echo $colors['border']; ?>;
            --success: <?php echo $colors['success']; ?>;
            --danger: <?php echo $colors['danger']; ?>;
            --warning: <?php echo $colors['warning']; ?>;
            --info: <?php echo $colors['info']; ?>;
            --font-size-base: <?php echo $font_size; ?>;
            --spacing-base: <?php echo $compact_mode ? '0.75rem' : '1rem'; ?>;
            --animation-speed: <?php echo $animation_time; ?>;
        }

        * {
            transition: <?php echo $animations ? 'all var(--animation-speed) ease' : 'none'; ?>;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            font-size: var(--font-size-base);
        }

        .main-content {
            margin-left: 260px;
            padding: 20px;
            transition: all 0.3s;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        <?php if ($compact_mode): ?>
        .card-body { padding: 0.75rem !important; }
        .btn { padding: 0.5rem 1rem !important; }
        .form-control, .form-select { padding: 0.375rem 0.75rem !important; }
        <?php endif; ?>

        .filter-bar {
            background: white;
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .students-table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        .students-table {
            width: 100%;
            font-size: 13px;
        }

        .students-table thead th {
            background: var(--primary-color);
            color: white;
            padding: 12px;
            text-align: center;
        }

        .students-table tbody td {
            padding: 10px;
            text-align: center;
            vertical-align: middle;
        }

        .students-table tbody tr:hover {
            background: #f8f9fa;
        }

        .btn-change-phone {
            background: var(--warning);
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-change-phone:hover {
            background: #e67e22;
            transform: scale(1.05);
        }

        /* Modal Styles - Centered */
        .custom-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }

        .custom-modal.show {
            display: flex;
        }

        .custom-modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            animation: modalSlideIn 0.3s ease;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .custom-modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 15px 20px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .custom-modal-header h5 {
            margin: 0;
            font-size: 1.1rem;
        }

        .custom-modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .custom-modal-close:hover {
            transform: scale(1.1);
        }

        .custom-modal-body {
            padding: 20px;
        }

        .custom-modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-color);
        }

        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(59, 157, 179, 0.1);
        }

        .form-group input:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }

        .phone-preview {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .btn-save {
            background: var(--success);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-save:hover {
            background: #1e7e34;
            transform: scale(1.02);
        }

        .btn-cancel {
            background: var(--danger);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #c82333;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--primary-color);
            border-left: 4px solid var(--primary-color);
            padding-left: 15px;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding-right: 40px;
        }

        .search-box button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
        }

        .student-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .student-info p {
            margin: 5px 0;
            font-size: 13px;
        }

        .student-info strong {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h2 style="color: var(--text-color);">
                    <i class="fas fa-phone-alt me-2" style="color: var(--primary-color);"></i>
                    Change Parent Phone Number
                </h2>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Select Form</label>
                        <select id="formSelector" class="form-select">
                            <option value="Form five" <?php echo $selected_form == 'Form five' ? 'selected' : ''; ?>>Form Five</option>
                            <option value="Form six" <?php echo $selected_form == 'Form six' ? 'selected' : ''; ?>>Form Six</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Search Student</label>
                        <div class="search-box">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search by name or index number..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button id="searchBtn"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">&nbsp;</label>
                        <a href="change_phone.php?form=<?php echo urlencode($selected_form); ?>" class="btn btn-outline-secondary d-block">
                            <i class="fas fa-sync-alt me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($students); ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php 
                            $with_phone = count(array_filter($students, function($s) {
                                return !empty($s['parent_phone']);
                            }));
                            echo $with_phone;
                            ?>
                        </div>
                        <div class="stat-label">Students with Phone Number</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php echo count($students) - $with_phone; ?>
                        </div>
                        <div class="stat-label">Students Missing Phone</div>
                    </div>
                </div>
            </div>

            <!-- Students Table -->
            <div class="students-table-container">
                <div class="section-title">
                    <i class="fas fa-users me-2"></i>Student List - <?php echo $display_form; ?>
                </div>
                <div class="table-responsive">
                    <table class="students-table" id="studentsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Index No</th>
                                <th>Student Name</th>
                                <th>Sex</th>
                                <th>Combination</th>
                                <th>Parent Name</th>
                                <th>Parent Phone</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr><td colspan="8" class="text-center py-5">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block text-muted"></i>
                                    No students found
                                </td></tr>
                            <?php else: ?>
                                <?php $counter = 1; foreach ($students as $student): ?>
                                    <tr data-student-id="<?php echo $student['id']; ?>">
                                        <td><?php echo $counter++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($student['index_number']); ?></strong></td>
                                        <td class="text-start">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            <?php if ($student['second_name']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($student['second_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $student['sex'] == 'Male' ? 'bg-primary' : 'bg-danger'; ?>">
                                                <?php echo $student['sex']; ?>
                                            </span>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo $student['combination']; ?></span></td>
                                        <td class="text-start"><?php echo htmlspecialchars($student['parent_name'] ?? '-'); ?></td>
                                        <td>
                                            <?php if (!empty($student['parent_phone'])): ?>
                                                <i class="fas fa-phone text-success me-1"></i>
                                                <?php echo htmlspecialchars($student['parent_phone_formatted']); ?>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="fas fa-ban"></i> No phone</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn-change-phone" onclick="openChangePhoneModal(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-edit me-1"></i> Change Phone
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Info Note -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Information:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Click <strong>Change Phone</strong> button to modify parent phone number and details</li>
                            <li>Phone numbers are automatically formatted to Tanzanian format (starting with 255)</li>
                            <li>Supported formats: 0712345678, 255712345678, or 712345678</li>
                            <li>Updated information will be used for SMS notifications</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Centered Modal -->
    <div id="phoneModal" class="custom-modal">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
                <h5><i class="fas fa-phone-alt me-2"></i>Change Parent Contact Information</h5>
                <button class="custom-modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="custom-modal-body">
                <div id="modalStudentInfo" class="student-info">
                    <p><strong>Loading student information...</strong></p>
                </div>
                <form id="phoneForm">
                    <input type="hidden" id="student_id" name="student_id">
                    <div class="form-group">
                        <label><i class="fas fa-user me-1"></i> Parent/Guardian Name</label>
                        <input type="text" id="parent_name" name="parent_name" class="form-control" placeholder="e.g., John Michael">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone me-1"></i> Phone Number</label>
                        <input type="tel" id="parent_phone" name="parent_phone" class="form-control" placeholder="e.g., 0712345678 or 255712345678">
                        <div class="phone-preview" id="phonePreview"></div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-briefcase me-1"></i> Occupation (Optional)</label>
                        <input type="text" id="parent_occupation" name="parent_occupation" class="form-control" placeholder="e.g., Farmer, Teacher, Business">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-home me-1"></i> Residence (Optional)</label>
                        <input type="text" id="parent_residence" name="parent_residence" class="form-control" placeholder="e.g., Muyovozi, Dar es Salaam">
                    </div>
                </form>
            </div>
            <div class="custom-modal-footer">
                <button class="btn-cancel" onclick="closeModal()">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button class="btn-save" onclick="savePhoneNumber()">
                    <i class="fas fa-save me-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Initialize DataTable
        $(document).ready(function() {
            if ($('#studentsTable tbody tr').length > 0) {
                $('#studentsTable').DataTable({
                    pageLength: 25,
                    language: {
                        search: "Search:",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries"
                    }
                });
            }
        });

        // Filter handlers
        $('#formSelector').on('change', function() {
            const form = $(this).val();
            window.location.href = `change_phone.php?form=${encodeURIComponent(form)}`;
        });

        $('#searchBtn').click(function() {
            const search = $('#searchInput').val();
            const form = $('#formSelector').val();
            window.location.href = `change_phone.php?form=${encodeURIComponent(form)}&search=${encodeURIComponent(search)}`;
        });

        $('#searchInput').keypress(function(e) {
            if (e.which === 13) {
                $('#searchBtn').click();
            }
        });

        // Open modal and load student data
        function openChangePhoneModal(studentId) {
            // Show modal with loading state
            $('#modalStudentInfo').html('<p><strong>Loading student information...</strong></p>');
            $('#student_id').val(studentId);
            $('#parent_name').val('');
            $('#parent_phone').val('');
            $('#parent_occupation').val('');
            $('#parent_residence').val('');
            $('#phonePreview').html('');
            
            // Show modal
            $('#phoneModal').addClass('show');
            
            // Fetch student details
            $.ajax({
                url: window.location.href,
                method: 'GET',
                data: {
                    get_student: 1,
                    student_id: studentId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const student = response.student;
                        const fullName = student.first_name + ' ' + student.last_name + (student.second_name ? ' ' + student.second_name : '');
                        
                        $('#modalStudentInfo').html(`
                            <p><strong><i class="fas fa-user-graduate me-1"></i> Student:</strong> ${fullName}</p>
                            <p><strong><i class="fas fa-id-card me-1"></i> Index:</strong> ${student.index_number}</p>
                            <p><strong><i class="fas fa-venus-mars me-1"></i> Gender:</strong> ${student.sex}</p>
                            <p><strong><i class="fas fa-layer-group me-1"></i> Combination:</strong> ${student.combination}</p>
                        `);
                        
                        $('#parent_name').val(student.parent_name || '');
                        $('#parent_phone').val(student.parent_phone_formatted || '');
                        $('#parent_occupation').val(student.parent_occupation || '');
                        $('#parent_residence').val(student.parent_residence || '');
                        
                        // Show phone preview
                        if (student.parent_phone_formatted) {
                            $('#phonePreview').html(`Will be saved as: <strong>255${student.parent_phone_formatted.substring(1)}</strong>`);
                        }
                    } else {
                        $('#modalStudentInfo').html(`<p class="text-danger">Error: ${response.error || 'Failed to load student'}</p>`);
                    }
                },
                error: function() {
                    $('#modalStudentInfo').html('<p class="text-danger">Error loading student information</p>');
                }
            });
        }

        // Close modal
        function closeModal() {
            $('#phoneModal').removeClass('show');
        }

        // Live phone number preview
        $('#parent_phone').on('input', function() {
            let phone = $(this).val();
            phone = phone.replace(/[^0-9]/g, '');
            if (phone.length > 0) {
                if (phone.substring(0, 1) === '0') {
                    $('#phonePreview').html(`Will be saved as: <strong>255${phone.substring(1)}</strong>`);
                } else if (phone.substring(0, 3) === '255') {
                    $('#phonePreview').html(`Will be saved as: <strong>${phone}</strong>`);
                } else if (phone.substring(0, 1) === '7' || phone.substring(0, 1) === '6') {
                    $('#phonePreview').html(`Will be saved as: <strong>255${phone}</strong>`);
                } else {
                    $('#phonePreview').html(`<span class="text-warning">Please enter a valid Tanzanian phone number</span>`);
                }
            } else {
                $('#phonePreview').html('');
            }
        });

        // Save phone number
        function savePhoneNumber() {
            const studentId = $('#student_id').val();
            const parentPhone = $('#parent_phone').val();
            const parentName = $('#parent_name').val();
            const parentOccupation = $('#parent_occupation').val();
            const parentResidence = $('#parent_residence').val();
            
            if (!studentId) {
                Swal.fire('Error', 'Invalid student ID', 'error');
                return;
            }
            
            // Show loading
            Swal.fire({
                title: 'Saving...',
                text: 'Please wait while we update the information',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    update_phone: 1,
                    student_id: studentId,
                    parent_phone: parentPhone,
                    parent_name: parentName,
                    parent_occupation: parentOccupation,
                    parent_residence: parentResidence
                },
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonColor: '#27ae60',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            closeModal();
                            // Reload the page to show updated data
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonColor: '#e74c3c'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.close();
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to save. Please try again.',
                        icon: 'error',
                        confirmButtonColor: '#e74c3c'
                    });
                    console.error('AJAX Error:', error);
                }
            });
        }

        // Close modal when clicking outside
        $(document).on('click', '#phoneModal', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Handle escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#phoneModal').hasClass('show')) {
                closeModal();
            }
        });
    </script>
</body>
</html>
<?php include '../controller/footer.php'; ?>