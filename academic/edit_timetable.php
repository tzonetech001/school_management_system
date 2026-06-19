<?php
// edit_timetable.php - Edit existing timetable
session_start();
require_once '../controller/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../mhs/login.php');
    exit();
}

$user_id = intval($_SESSION['admin_id']);
$timetable_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($timetable_id == 0) {
    header('Location: teacher_timetable.php');
    exit();
}

// Get timetable details
$timetable_query = "SELECT * FROM generated_timetables WHERE id = ?";
$stmt = $conn->prepare($timetable_query);
$stmt->bind_param("i", $timetable_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: teacher_timetable.php');
    exit();
}

$timetable = $result->fetch_assoc();

// Check permission
$has_admin_role = false;
$admin_roles_query = "SELECT ar.role_name FROM admin_role_assignments ara 
                      JOIN admin_roles ar ON ara.role_id = ar.id 
                      WHERE ara.admin_id = $user_id 
                      AND ar.role_name IN ('Head Master', 'Second Master', 'Academic Master')";
$admin_roles_result = mysqli_query($conn, $admin_roles_query);
if ($admin_roles_result && mysqli_num_rows($admin_roles_result) > 0) {
    $has_admin_role = true;
}

if (!$has_admin_role && $timetable['generated_by'] != $user_id) {
    $_SESSION['error'] = "You don't have permission to edit this timetable.";
    header('Location: teacher_timetable.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get all form data
    $term = isset($_POST['term']) ? $_POST['term'] : $timetable['term'];
    $year = isset($_POST['year']) ? intval($_POST['year']) : $timetable['year'];
    $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : $timetable['start_time'];
    $session_length = isset($_POST['session_length']) ? intval($_POST['session_length']) : $timetable['session_length'];
    $sessions_per_day = isset($_POST['sessions_per_day']) ? intval($_POST['sessions_per_day']) : $timetable['sessions_per_day'];
    $break_after = isset($_POST['break_after']) ? intval($_POST['break_after']) : $timetable['break_after'];
    $break_length = isset($_POST['break_length']) ? intval($_POST['break_length']) : $timetable['break_length'];
    $days = isset($_POST['days']) ? $_POST['days'] : explode(', ', $timetable['days']);
    
    // Store in session for regenerate
    $_SESSION['edit_params'] = [
        'timetable_id' => $timetable_id,
        'term' => $term,
        'year' => $year,
        'start_time' => $start_time,
        'session_length' => $session_length,
        'sessions_per_day' => $sessions_per_day,
        'break_after' => $break_after,
        'break_length' => $break_length,
        'days' => $days
    ];
    
    header('Location: regenerate_timetable.php');
    exit();
}

// Get school_id
$school_query = "SELECT school_id FROM admins WHERE id = $user_id";
$school_result = mysqli_query($conn, $school_query);
$school_data = mysqli_fetch_assoc($school_result);
$school_id = $school_data['school_id'];

// Get combinations for display
$form5_combinations = [];
$form5_result = mysqli_query($conn, "SELECT DISTINCT combination FROM students WHERE class = 'Form Five' AND school_id = $school_id AND (is_leaver = 0 OR is_leaver IS NULL) AND combination IS NOT NULL AND combination != '' ORDER BY combination");
while ($row = mysqli_fetch_assoc($form5_result)) { $form5_combinations[] = $row['combination']; }

$form6_combinations = [];
$form6_result = mysqli_query($conn, "SELECT DISTINCT combination FROM students WHERE class = 'Form Six' AND school_id = $school_id AND (is_leaver = 0 OR is_leaver IS NULL) AND combination IS NOT NULL AND combination != '' ORDER BY combination");
while ($row = mysqli_fetch_assoc($form6_result)) { $form6_combinations[] = $row['combination']; }

// Get days from stored value
$current_days = !empty($timetable['days']) ? explode(', ', $timetable['days']) : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$all_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Load theme settings
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
    <title>Edit Timetable</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); overflow: hidden; }
        .card-header { border: none; }
        .form-label { font-weight: 600; color: #333; margin-bottom: 8px; }
        .form-control, .form-select { border-radius: 8px; border: 1px solid #e0e0e0; padding: 10px 12px; }
        .btn-primary { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); border: none; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(59, 157, 179, 0.3); }
        .form-check-input:checked { background-color: var(--primary-color); border-color: var(--primary-color); }
        
        .breadcrumb-custom {
            background: white;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .preview-box {
            background: #e8f4f8;
            padding: 10px 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Breadcrumb -->
            <div class="breadcrumb-custom">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="timetable.php" style="color: var(--primary-color);">
                                <i class="fas fa-home me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="timetable.php" style="color: var(--primary-color);">Timetable</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Edit Timetable</li>
                    </ol>
                </nav>
            </div>

            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                    <h5><i class="fas fa-edit me-2"></i>Edit Timetable</h5>
                    <p class="mb-0 small opacity-75">Edit timetable settings and regenerate</p>
                </div>
                <div class="card-body">
                    <!-- Current Timetable Info -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Editing:</strong> <?php echo htmlspecialchars($timetable['term']); ?> - <?php echo $timetable['year']; ?>
                        <br>
                        <small>Generated by: <?php 
                            $gen_query = "SELECT first_name, last_name FROM admins WHERE id = " . $timetable['generated_by'];
                            $gen_result = mysqli_query($conn, $gen_query);
                            if ($gen_result && $row = mysqli_fetch_assoc($gen_result)) {
                                echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                            } else {
                                echo 'Unknown';
                            }
                        ?></small>
                    </div>
                    
                    <!-- Class Combinations Info -->
                    <div class="alert alert-secondary">
                        <i class="fas fa-users me-2"></i>
                        <strong>Classes included:</strong>
                        <?php 
                        $all_classes = array_merge($form5_combinations, $form6_combinations);
                        echo count($all_classes) . ' classes (Form 5: ' . count($form5_combinations) . ', Form 6: ' . count($form6_combinations) . ')';
                        ?>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-tag me-1"></i>Term</label>
                                <select name="term" class="form-select" required>
                                    <option value="Term 01" <?php echo $timetable['term'] == 'Term 01' ? 'selected' : ''; ?>>Term 01</option>
                                    <option value="Term 02" <?php echo $timetable['term'] == 'Term 02' ? 'selected' : ''; ?>>Term 02</option>
                                </select>
                                <div class="form-text">Document name will be: <strong id="docPreview"><?php echo $timetable['term'] . ' Timetable - ' . $timetable['year']; ?></strong></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-calendar me-1"></i>Year</label>
                                <input type="number" name="year" class="form-control" id="yearInput" value="<?php echo $timetable['year']; ?>" min="2020" max="2030" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-clock me-1"></i>Start Time</label>
                                <input type="time" name="start_time" class="form-control" value="<?php echo $timetable['start_time'] ?? '08:00'; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-hourglass-half me-1"></i>Session Length (min)</label>
                                <input type="number" name="session_length" class="form-control" value="<?php echo $timetable['session_length'] ?? 40; ?>" min="10" max="120" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-layer-group me-1"></i>Sessions per Day</label>
                                <select name="sessions_per_day" class="form-select" required>
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($timetable['sessions_per_day'] ?? 6) == $i ? 'selected' : ''; ?>>
                                            <?php echo $i; ?> Session(s)
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-mug-hot me-1"></i>Break After Session</label>
                                <select name="break_after" class="form-select">
                                    <option value="0">No Break</option>
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($timetable['break_after'] ?? 2) == $i ? 'selected' : ''; ?>>
                                            After Session <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-coffee me-1"></i>Break Length (min)</label>
                                <input type="number" name="break_length" class="form-control" value="<?php echo $timetable['break_length'] ?? 30; ?>" min="0" max="90">
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <label class="form-label"><i class="fas fa-calendar-week me-1"></i>Days</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <?php foreach ($all_days as $day): ?>
                                        <div class="form-check">
                                            <input type="checkbox" name="days[]" value="<?php echo $day; ?>" 
                                                   id="day_<?php echo $day; ?>" 
                                                   class="form-check-input"
                                                   <?php echo in_array($day, $current_days) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="day_<?php echo $day; ?>">
                                                <?php echo $day; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Select the days to include in the timetable</div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save & Regenerate
                            </button>
                            <a href="timetable.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../controller/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Update document preview when term or year changes
            function updateDocPreview() {
                const term = $('select[name="term"]').val();
                const year = $('#yearInput').val();
                $('#docPreview').text(term + ' Timetable - ' + year);
            }
            
            $('select[name="term"], #yearInput').on('change keyup', updateDocPreview);
            updateDocPreview();
        });
    </script>
</body>
</html>