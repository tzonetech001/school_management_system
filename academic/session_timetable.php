<?php
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
$form5_combinations_query = "SELECT DISTINCT combination 
                              FROM students 
                              WHERE class = 'Form Five' 
                              AND school_id = $school_id 
                              AND (is_leaver = 0 OR is_leaver IS NULL)
                              AND combination IS NOT NULL 
                              AND combination != ''
                              ORDER BY combination";
$form5_result = mysqli_query($conn, $form5_combinations_query);
$form5_combinations = [];
if ($form5_result && mysqli_num_rows($form5_result) > 0) {
    while ($row = mysqli_fetch_assoc($form5_result)) {
        $form5_combinations[] = $row['combination'];
    }
}

// Get unique combinations for Form Six
$form6_combinations_query = "SELECT DISTINCT combination 
                              FROM students 
                              WHERE class = 'Form Six' 
                              AND school_id = $school_id 
                              AND (is_leaver = 0 OR is_leaver IS NULL)
                              AND combination IS NOT NULL 
                              AND combination != ''
                              ORDER BY combination";
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
    'Saturday' => ''  // Unchecked by default
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Session Timetable - School Management System</title>
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
        .main-content { margin-left: 260px; padding: 20px; min-height: 100vh; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } }
        <?php if ($compact_mode): ?>.card-body { padding: 0.75rem !important; }<?php endif; ?>

        .card-custom { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 25px; }
        .card-header-custom { background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%); color: white; padding: 15px 25px; border: none; }
        .card-header-custom h5 { margin: 0; font-weight: 600; }
        .form-label { font-weight: 600; color: #333; margin-bottom: 8px; }
        .form-control, .form-select { border-radius: 8px; border: 1px solid #e0e0e0; padding: 10px 12px; }
        .btn-primary-custom { background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%); border: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; color: white; cursor: pointer; }
        .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(59, 157, 179, 0.3); }
        .btn-secondary-custom { background: #6c757d; border: none; padding: 10px 25px; border-radius: 8px; font-weight: 600; color: white; text-decoration: none; display: inline-block; }
        .combinations-list { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-top: 10px; max-height: 200px; overflow-y: auto; }
        .combination-badge { display: inline-block; background: var(--primary-light); color: var(--primary-dark); padding: 5px 12px; border-radius: 20px; margin: 3px; font-size: 12px; font-weight: 600; }
        .form-section { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .section-title { font-size: 18px; font-weight: 600; color: var(--primary-color); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--primary-light); }
        .section-title i { margin-right: 10px; }
        .info-box { background: #e8f4f8; border-left: 4px solid var(--primary-color); padding: 12px 15px; border-radius: 8px; margin-top: 15px; }
        .day-checkbox-group { display: flex; flex-wrap: wrap; gap: 15px; }
        .day-checkbox { background: white; border: 2px solid #e0e0e0; border-radius: 10px; padding: 10px 20px; cursor: pointer; transition: all 0.3s; min-width: 100px; text-align: center; }
        .day-checkbox:hover { border-color: var(--primary-color); background: #f8f9fa; }
        .day-checkbox.selected { border-color: var(--primary-color); background: rgba(59, 157, 179, 0.1); }
        .day-checkbox input { margin-right: 8px; }
        .alert-custom { border-radius: 10px; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .alert-info-custom { background: rgba(23, 162, 184, 0.1); border-left: 4px solid var(--info-color); }
        .range-value { display: inline-block; margin-left: 10px; padding: 2px 8px; background: var(--primary-light); border-radius: 20px; font-size: 12px; }
    </style>
</head>
<body>
<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="card-custom">
            <div class="card-header-custom">
                <h5><i class="fas fa-calendar-alt"></i> Create Session Timetable - Form 5 & Form 6</h5>
                <p class="mb-0 mt-2 small opacity-75">Configure your timetable parameters. Days on the left, Time at the top.</p>
            </div>
            <div class="card-body p-4">
                <form id="timetableForm" method="post" action="generate_session_timetable.php" target="_blank">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <!-- Class Combinations -->
                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-users"></i> Class Combinations</div>
                        <div class="info-box"><i class="fas fa-info-circle"></i> <strong>Timetable will be generated for:</strong></div>
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

                    <!-- Basic Information -->
                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-info-circle"></i> Basic Information</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-tag me-1"></i>Term <span class="text-danger">*</span></label>
                                <select name="term" class="form-select" id="termSelect" required>
                                    <option value="Term 1">📘 Term 1</option>
                                    <option value="Term 2" selected>📙 Term 2</option>
                                </select>
                                <div class="form-text text-muted">Document name will be: [Term] Timetable - [Year]</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-calendar me-1"></i>Academic Year <span class="text-danger">*</span></label>
                                <input type="number" name="year" class="form-control" value="<?php echo $current_year; ?>" min="2020" max="2030" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-clock me-1"></i>Start Time <span class="text-danger">*</span></label>
                                <input type="time" name="start_time" class="form-control" value="08:00" required>
                            </div>
                        </div>
                    </div>

                    <!-- Time Configuration -->
                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-clock"></i> Time Configuration</div>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-hourglass-half me-1"></i>Session Length (minutes)</label>
                                <input type="number" name="session_length" class="form-control" value="40" min="10" max="120" required>
                                <div class="form-text">Each session duration</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-layer-group me-1"></i>Sessions per Day <span class="text-danger">*</span></label>
                                <select name="sessions_per_day" class="form-select" id="sessionsPerDay" required>
                                    <option value="1">1 Session per day</option>
                                    <option value="2">2 Sessions per day</option>
                                    <option value="3">3 Sessions per day</option>
                                    <option value="4">4 Sessions per day</option>
                                    <option value="5">5 Sessions per day</option>
                                    <option value="6" selected>6 Sessions per day</option>
                                    <option value="7">7 Sessions per day</option>
                                    <option value="8">8 Sessions per day</option>
                                </select>
                                <div class="form-text">Maximum 8 sessions</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-mug-hot me-1"></i>Break After Session</label>
                                <select name="break_after" class="form-select" id="breakAfterSelect">
                                    <option value="0">No Break</option>
                                    <option value="1">After Session 1</option>
                                    <option value="2" selected>After Session 2</option>
                                    <option value="3">After Session 3</option>
                                    <option value="4">After Session 4</option>
                                    <option value="5">After Session 5</option>
                                    <option value="6">After Session 6</option>
                                    <option value="7">After Session 7</option>
                                    <option value="8">After Session 8</option>
                                </select>
                                <div class="form-text">Break will be placed after selected session</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-coffee me-1"></i>Break Length (minutes)</label>
                                <input type="number" name="break_length" class="form-control" value="30" min="0" max="90" id="breakLength">
                                <div class="form-text">Duration of break</div>
                            </div>
                        </div>
                        
                        <!-- Dynamic preview -->
                        <div class="alert alert-info-custom mt-3" id="schedulePreview">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Schedule Preview:</strong> 
                            Start at <span id="previewStartTime">08:00</span>, 
                            <span id="previewSessionsCount">6</span> sessions of <span id="previewSessionLength">40</span> minutes each.
                            <span id="previewBreakInfo">Break after session <strong id="previewBreakAfter">2</strong> for <strong id="previewBreakLength">30</strong> minutes.</span>
                            <br><small>Total time: <strong id="previewTotalTime">6 hours 30 minutes</strong></small>
                        </div>
                    </div>

                    <!-- Days Selection -->
                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-calendar-week"></i> Select Days</div>
                        <div class="day-checkbox-group" id="daysContainer">
                            <?php foreach ($days_of_week as $day => $checked): ?>
                                <div class="day-checkbox <?php echo $checked ? 'selected' : ''; ?>" data-day="<?php echo $day; ?>">
                                    <input type="checkbox" name="days[]" value="<?php echo $day; ?>" id="day_<?php echo $day; ?>" <?php echo $checked; ?>>
                                    <label for="day_<?php echo $day; ?>" class="mb-0"><i class="fas fa-calendar-day me-1"></i><?php echo $day; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text text-muted mt-2"><i class="fas fa-info-circle"></i> Saturday is optional - check if you want to include it</div>
                    </div>

                    <!-- Export Options -->
                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-download"></i> Export Options</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-file-excel me-1"></i>Export Format</label>
                                <select name="export_format" class="form-select" required>
                                    <option value="excel" selected>📊 Excel Format (.xls)</option>
                                    <option value="pdf">📄 PDF Format (Print/Save)</option>
                                    <option value="csv">📑 CSV Format</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-envelope me-1"></i>Notify Teachers</label>
                                <select name="notify_teachers" class="form-select">
                                    <option value="1">Yes, send email notifications</option>
                                    <option value="0" selected>No, don't send notifications</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-save me-1"></i>Action</label>
                                <select name="action" class="form-select">
                                    <option value="download">Download File</option>
                                    <option value="view">View in Browser</option>
                                    <option value="save">Save to Server</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="text-end mt-3">
                        <a href="timetable.php" class="btn btn-secondary-custom me-2"><i class="fas fa-arrow-left me-1"></i> Cancel</a>
                        <button type="button" id="generateBtn" class="btn btn-primary-custom"><i class="fas fa-play me-1"></i> Generate Timetable</button>
                    </div>

                    <!-- Confirmation Modal -->
                    <div class="modal fade" id="confirmModal" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header" style="background: var(--primary-color); color: white;">
                                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Confirm Generation</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        <strong>Timetable Summary:</strong>
                                        <ul class="mt-2 mb-0">
                                            <li>Document Name: <strong id="summaryDocName"></strong></li>
                                            <li>Start Time: <strong id="summaryStartTime"></strong></li>
                                            <li>Sessions per Day: <strong id="summarySessionsPerDay"></strong></li>
                                            <li>Break: <strong id="summaryBreakInfo"></strong></li>
                                            <li>Selected Days: <strong id="summaryDays"></strong></li>
                                            <li>Total Classes: <strong><?php echo count($form5_combinations) + count($form6_combinations); ?></strong></li>
                                        </ul>
                                    </div>
                                    <p class="mb-0">Generate timetable for all Form 5 and Form 6 combinations?</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" id="confirmGenerate" class="btn btn-primary">Yes, Generate</button>
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
                                    <h5>Generating Timetable...</h5>
                                    <p>Please wait while we create your timetable.</p>
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
    // Function to update preview
    function updatePreview() {
        const startTime = $('input[name="start_time"]').val();
        const sessionLength = parseInt($('input[name="session_length"]').val()) || 0;
        const sessionsPerDay = parseInt($('#sessionsPerDay').val()) || 0;
        const breakAfter = parseInt($('#breakAfterSelect').val()) || 0;
        const breakLength = parseInt($('#breakLength').val()) || 0;
        
        $('#previewStartTime').text(startTime);
        $('#previewSessionsCount').text(sessionsPerDay);
        $('#previewSessionLength').text(sessionLength);
        
        if (breakAfter > 0 && breakAfter <= sessionsPerDay) {
            $('#previewBreakInfo').html('Break after session <strong>' + breakAfter + '</strong> for <strong>' + breakLength + '</strong> minutes.');
        } else if (breakAfter > sessionsPerDay) {
            $('#previewBreakInfo').html('<span class="text-warning">Break after session ' + breakAfter + ' (exceeds sessions per day, will be ignored)</span>');
        } else {
            $('#previewBreakInfo').html('No break scheduled');
        }
        
        // Calculate total time
        let totalMinutes = sessionsPerDay * sessionLength;
        if (breakAfter > 0 && breakAfter <= sessionsPerDay) {
            totalMinutes += breakLength;
        }
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;
        $('#previewTotalTime').text(hours + ' hour(s) ' + minutes + ' minutes');
    }
    
    // Update options for break after based on sessions per day
    function updateBreakAfterOptions() {
        const sessionsPerDay = parseInt($('#sessionsPerDay').val());
        const breakAfterSelect = $('#breakAfterSelect');
        const currentValue = parseInt(breakAfterSelect.val());
        
        breakAfterSelect.empty();
        breakAfterSelect.append('<option value="0">No Break</option>');
        for (let i = 1; i <= sessionsPerDay; i++) {
            breakAfterSelect.append('<option value="' + i + '">After Session ' + i + '</option>');
        }
        
        if (currentValue > 0 && currentValue <= sessionsPerDay) {
            breakAfterSelect.val(currentValue);
        } else {
            breakAfterSelect.val(2 <= sessionsPerDay ? 2 : (sessionsPerDay > 0 ? sessionsPerDay : 0));
        }
        
        updatePreview();
    }
    
    // Event listeners
    $('input[name="start_time"], input[name="session_length"], #breakLength').on('change keyup', updatePreview);
    $('#sessionsPerDay').on('change', updateBreakAfterOptions);
    $('#breakAfterSelect').on('change', updatePreview);
    
    updateBreakAfterOptions();
    
    // Day checkbox styling
    $('.day-checkbox').click(function(e) {
        if (e.target.type !== 'checkbox') {
            const checkbox = $(this).find('input[type="checkbox"]');
            checkbox.prop('checked', !checkbox.prop('checked'));
        }
        $(this).toggleClass('selected', $(this).find('input[type="checkbox"]').prop('checked'));
    });
    $('.day-checkbox input').change(function() { $(this).closest('.day-checkbox').toggleClass('selected', this.checked); });

    $('#generateBtn').click(function() {
        if ($('input[name="days[]"]:checked').length === 0) { alert('Please select at least one day.'); return; }
        const sessionsPerDay = parseInt($('#sessionsPerDay').val());
        if (isNaN(sessionsPerDay) || sessionsPerDay < 1) { alert('Sessions per day must be at least 1.'); return; }
        const sessionLength = parseInt($('input[name="session_length"]').val());
        if (isNaN(sessionLength) || sessionLength < 10) { alert('Session length must be at least 10 minutes.'); return; }
        updateModalSummary();
        new bootstrap.Modal(document.getElementById('confirmModal')).show();
    });

    $('#confirmGenerate').click(function() {
        $('#confirmModal').modal('hide');
        new bootstrap.Modal(document.getElementById('processingModal')).show();
        setTimeout(() => { $('#timetableForm').submit(); setTimeout(() => { $('#processingModal').modal('hide'); }, 2000); }, 500);
    });

    function updateModalSummary() {
        const term = $('#termSelect option:selected').text();
        const year = $('input[name="year"]').val();
        const startTime = $('input[name="start_time"]').val();
        const sessionsPerDay = $('#sessionsPerDay').val();
        const breakAfter = $('#breakAfterSelect').val();
        const breakLength = $('#breakLength').val();
        const selectedDays = $('input[name="days[]"]:checked').map(function() { return $(this).val(); }).get().join(', ');
        
        $('#summaryDocName').text(term + ' Timetable - ' + year);
        $('#summaryStartTime').text(startTime);
        $('#summarySessionsPerDay').text(sessionsPerDay);
        $('#summaryBreakInfo').text(breakAfter > 0 ? 'After Session ' + breakAfter + ' for ' + breakLength + ' minutes' : 'No break');
        $('#summaryDays').text(selectedDays);
    }
});
</script>
</body>
</html>