<?php
// session_timetable.php - FIXED term passing
session_start();
require_once '../controller/db_connect.php';

// Check login
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../mhs/login.php');
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$current_year = date('Y');
$admin_id = intval($_SESSION['admin_id']);

// Get school_id for the current admin
$school_query = "SELECT school_id FROM admins WHERE id = $admin_id";
$school_result = mysqli_query($conn, $school_query);
$school_data = mysqli_fetch_assoc($school_result);
$school_id = $school_data['school_id'];

// Get unique combinations for Form Five
$form5_combinations_query = "SELECT DISTINCT combination FROM students WHERE class = 'Form Five' AND school_id = $school_id AND (is_leaver = 0 OR is_leaver IS NULL) AND combination IS NOT NULL AND combination != '' ORDER BY combination";
$form5_result = mysqli_query($conn, $form5_combinations_query);
$form5_combinations = [];
if ($form5_result && mysqli_num_rows($form5_result) > 0) {
    while ($row = mysqli_fetch_assoc($form5_result)) {
        $form5_combinations[] = $row['combination'];
    }
}

// Get unique combinations for Form Six
$form6_combinations_query = "SELECT DISTINCT combination FROM students WHERE class = 'Form Six' AND school_id = $school_id AND (is_leaver = 0 OR is_leaver IS NULL) AND combination IS NOT NULL AND combination != '' ORDER BY combination";
$form6_result = mysqli_query($conn, $form6_combinations_query);
$form6_combinations = [];
if ($form6_result && mysqli_num_rows($form6_result) > 0) {
    while ($row = mysqli_fetch_assoc($form6_result)) {
        $form6_combinations[] = $row['combination'];
    }
}

// Fallback if no combinations found
if (empty($form5_combinations)) {
    $form5_combinations = ['HGE', 'HGL', 'HGK', 'PCM', 'CBG', 'EGM', 'HGM'];
}
if (empty($form6_combinations)) {
    $form6_combinations = ['HGE', 'HGL', 'HGK', 'PCM', 'CBG', 'EGM', 'HGM'];
}

// Days of the week - Saturday unchecked by default
$days_of_week = [
    'Monday' => 'checked',
    'Tuesday' => 'checked',
    'Wednesday' => 'checked',
    'Thursday' => 'checked',
    'Friday' => 'checked',
    'Saturday' => ''
];

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
    'border' => '#e0e0e0'
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

$csrf_token = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Session Timetable</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $colors['primary']; ?>;
            --primary-dark: <?php echo $colors['primary_dark']; ?>;
            --primary-light: <?php echo $colors['primary_light']; ?>;
            --text-color: <?php echo $colors['text']; ?>;
            --text-light: <?php echo $colors['text_light']; ?>;
            --border-color: <?php echo $colors['border']; ?>;
            --font-size-base: <?php echo $font_size; ?>;
            --spacing-base: <?php echo $compact_mode ? '0.75rem' : '1rem'; ?>;
            --animation-speed: <?php echo $animation_time; ?>;
        }
        * { transition: <?php echo $animations ? 'all var(--animation-speed) ease' : 'none'; ?>; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; font-size: var(--font-size-base); }
        .main-content { margin-left: 260px; padding: 20px; min-height: 100vh; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } }
        .card-custom { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 25px; }
        .card-header-custom { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; padding: 15px 25px; border: none; }
        .form-label { font-weight: 600; color: #333; margin-bottom: 8px; }
        .form-control, .form-select { border-radius: 8px; border: 1px solid #e0e0e0; padding: 10px 12px; }
        .btn-primary-custom { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); border: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; color: white; cursor: pointer; }
        .btn-secondary-custom { background: #6c757d; border: none; padding: 10px 25px; border-radius: 8px; font-weight: 600; color: white; text-decoration: none; display: inline-block; }
        .combinations-list { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-top: 10px; max-height: 200px; overflow-y: auto; }
        .combination-badge { display: inline-block; background: var(--primary-light); color: var(--primary-dark); padding: 5px 12px; border-radius: 20px; margin: 3px; font-size: 12px; font-weight: 600; }
        .form-section { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .section-title { font-size: 18px; font-weight: 600; color: var(--primary-color); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--primary-light); }
        .day-checkbox-group { display: flex; flex-wrap: wrap; gap: 15px; }
        .day-checkbox { background: white; border: 2px solid #e0e0e0; border-radius: 10px; padding: 10px 20px; cursor: pointer; transition: all 0.3s; min-width: 100px; text-align: center; }
        .day-checkbox:hover { border-color: var(--primary-color); background: #f8f9fa; }
        .day-checkbox.selected { border-color: var(--primary-color); background: rgba(59, 157, 179, 0.1); }
        .day-checkbox input { margin-right: 8px; }
        .alert-info-custom { background: rgba(23, 162, 184, 0.1); border-left: 4px solid #17a2b8; }
        .document-preview { background: #e8f4f8; padding: 10px 15px; border-radius: 8px; border-left: 4px solid var(--primary-color); margin-top: 10px; }
    </style>
</head>
<body>
<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="card-custom">
            <div class="card-header-custom">
                <h5><i class="fas fa-calendar-alt"></i> Create Session Timetable</h5>
                <p class="mb-0 mt-2 small opacity-75">Configure your timetable parameters. Days on the left, Time at the top.</p>
            </div>
            <div class="card-body p-4">
                <form id="timetableForm" method="post" action="generate_session_timetable.php" target="_blank">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-users"></i> Class Combinations</div>
                        <div class="combinations-list">
                            <strong>Form 5 Combinations (<?php echo count($form5_combinations); ?>):</strong><br>
                            <?php foreach ($form5_combinations as $combo): ?>
                                <span class="combination-badge">Form 5 - <?php echo htmlspecialchars($combo); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="combinations-list mt-2">
                            <strong>Form 6 Combinations (<?php echo count($form6_combinations); ?>):</strong><br>
                            <?php foreach ($form6_combinations as $combo): ?>
                                <span class="combination-badge">Form 6 - <?php echo htmlspecialchars($combo); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-info-circle"></i> Basic Information</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Term <span class="text-danger">*</span></label>
                                <select name="term" class="form-select" id="termSelect" required>
                                    <option value="Term 01">Term 01</option>
                                    <option value="Term 02" selected>Term 02</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                                <input type="number" name="year" class="form-control" id="yearInput" value="<?php echo $current_year; ?>" min="2020" max="2030" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Start Time <span class="text-danger">*</span></label>
                                <input type="time" name="start_time" class="form-control" value="08:00" required>
                            </div>
                        </div>
                        
                        <div class="document-preview mt-3">
                            <i class="fas fa-file-signature me-2"></i>
                            <strong>Document Name:</strong> 
                            <span id="documentNameDisplay" style="font-weight:bold;color:var(--primary-dark);">Term 02 Timetable - <?php echo $current_year; ?></span>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-clock"></i> Time Configuration</div>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Session Length (minutes)</label>
                                <input type="number" name="session_length" class="form-control" value="40" min="10" max="120" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sessions per Day <span class="text-danger">*</span></label>
                                <select name="sessions_per_day" class="form-select" id="sessionsPerDay" required>
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $i == 6 ? 'selected' : ''; ?>><?php echo $i; ?> Session(s)</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Break After Session</label>
                                <select name="break_after" class="form-select" id="breakAfterSelect">
                                    <option value="0">No Break</option>
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $i == 2 ? 'selected' : ''; ?>>After Session <?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Break Length (minutes)</label>
                                <input type="number" name="break_length" class="form-control" value="30" min="0" max="90">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-calendar-week"></i> Select Days</div>
                        <div class="day-checkbox-group">
                            <?php foreach ($days_of_week as $day => $checked): ?>
                                <div class="day-checkbox <?php echo $checked ? 'selected' : ''; ?>" data-day="<?php echo $day; ?>">
                                    <input type="checkbox" name="days[]" value="<?php echo $day; ?>" <?php echo $checked; ?>>
                                    <label class="mb-0"><i class="fas fa-calendar-day me-1"></i><?php echo $day; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-download"></i> Export Options</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Export Format</label>
                                <select name="export_format" class="form-select" required>
                                    <option value="excel" selected>Excel (.xls)</option>
                                    <option value="pdf">PDF (Print)</option>
                                    <option value="csv">CSV</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Action</label>
                                <select name="action" class="form-select">
                                    <option value="download">Download</option>
                                    <option value="view">View</option>
                                    <option value="save">Save</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <a href="timetable.php" class="btn btn-secondary-custom me-2"><i class="fas fa-arrow-left me-1"></i> Cancel</a>
                        <button type="button" id="generateBtn" class="btn btn-primary-custom"><i class="fas fa-play me-1"></i> Generate Timetable</button>
                    </div>

                    <!-- Confirmation Modal -->
                    <div class="modal fade" id="confirmModal" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header" style="background: var(--primary-color); color: white;">
                                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Confirm</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        <strong>Document:</strong> <span id="summaryDocName">Term 02 Timetable - <?php echo $current_year; ?></span>
                                    </div>
                                    <p>Generate timetable for all Form 5 and Form 6 combinations?</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" id="confirmGenerate" class="btn btn-primary">Generate</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Processing Modal -->
                    <div class="modal fade" id="processingModal" data-bs-backdrop="static" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-body text-center py-5">
                                    <i class="fas fa-spinner fa-spin fa-3x mb-3" style="color: var(--primary-color);"></i>
                                    <h5>Generating...</h5>
                                    <div class="progress mt-3"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div></div>
                                </div>
                            </div>
                        </div>
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
    function updateDocumentName() {
        const term = $('#termSelect').val();
        const year = $('#yearInput').val();
        const docName = term + ' Timetable - ' + year;
        $('#documentNameDisplay').text(docName);
        $('#summaryDocName').text(docName);
    }
    
    $('#termSelect, #yearInput').on('change keyup', updateDocumentName);
    updateDocumentName();
    
    $('.day-checkbox').click(function(e) {
        if (e.target.type !== 'checkbox') {
            const checkbox = $(this).find('input[type="checkbox"]');
            checkbox.prop('checked', !checkbox.prop('checked'));
        }
        $(this).toggleClass('selected', $(this).find('input[type="checkbox"]').prop('checked'));
    });

    $('#generateBtn').click(function() {
        if ($('input[name="days[]"]:checked').length === 0) { alert('Please select at least one day.'); return; }
        new bootstrap.Modal(document.getElementById('confirmModal')).show();
    });

    $('#confirmGenerate').click(function() {
        $('#confirmModal').modal('hide');
        new bootstrap.Modal(document.getElementById('processingModal')).show();
        setTimeout(() => { 
            $('#timetableForm').submit(); 
            setTimeout(() => { $('#processingModal').modal('hide'); }, 2000); 
        }, 500);
    });
});
</script>
</body>
</html>