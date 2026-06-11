<?php
// teacher_timetable.php - Teachers view for timetables
session_start();
require_once '../controller/db_connect.php';

// Check if teacher is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../mhs/login.php');
    exit();
}

$teacher_id = intval($_SESSION['admin_id']);

// Get teacher's school_id
$school_query = "SELECT school_id FROM admins WHERE id = $teacher_id";
$school_result = mysqli_query($conn, $school_query);
$school_data = mysqli_fetch_assoc($school_result);
$school_id = $school_data['school_id'];

// Get teacher's name
$teacher_query = "SELECT first_name, last_name FROM admins WHERE id = $teacher_id";
$teacher_result = mysqli_query($conn, $teacher_query);
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_name = $teacher['first_name'] . ' ' . $teacher['last_name'];

// Get available timetables from database
$timetables_query = "SELECT * FROM generated_timetables 
                     WHERE school_id = $school_id 
                     ORDER BY year DESC, 
                     FIELD(term, 'Term 2', 'Term 1')";
$timetables_result = mysqli_query($conn, $timetables_query);
$available_timetables = [];

if ($timetables_result && mysqli_num_rows($timetables_result) > 0) {
    while ($row = mysqli_fetch_assoc($timetables_result)) {
        $available_timetables[] = $row;
    }
}

// Load theme settings
$theme_settings = [];
$settings_query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $teacher_id";
$settings_result = mysqli_query($conn, $settings_query);
if ($settings_result && mysqli_num_rows($settings_result) > 0) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $theme_settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Load user preferences
$preferences = [];
$prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = $teacher_id";
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Timetable - Teacher Portal</title>
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
        <?php if ($compact_mode): ?>.card-body { padding: 0.75rem !important; }<?php endif; ?>

        .welcome-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: 15px;
            padding: 25px;
            color: white;
            margin-bottom: 25px;
        }

        .timetable-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            height: 100%;
        }

        .timetable-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        .timetable-card-header { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; padding: 15px; text-align: center; }
        .timetable-card-body { padding: 20px; text-align: center; }
        .timetable-icon { font-size: 48px; color: var(--primary-color); margin-bottom: 15px; }

        .btn-download { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; border: none; padding: 10px 20px; border-radius: 8px; margin: 5px; cursor: pointer; }
        .btn-download:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(59, 157, 179, 0.3); }
        .btn-view { background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 8px; margin: 5px; cursor: pointer; }
        .btn-view:hover { background: #5a6268; transform: translateY(-2px); }

        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 12px; }
        .empty-icon { font-size: 80px; color: var(--primary-light); margin-bottom: 20px; }
        .filter-section { background: white; border-radius: 12px; padding: 15px; margin-bottom: 25px; }
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
                        <h2 class="mb-2"><i class="fas fa-chalkboard-teacher me-2"></i>Welcome, <?php echo htmlspecialchars($teacher_name); ?></h2>
                        <p class="mb-0 opacity-75"><i class="fas fa-calendar-alt me-2"></i>View and download timetables for your classes</p>
                    </div>
                    <div class="mt-3 mt-sm-0">
                        <span class="badge bg-light text-dark"><i class="fas fa-file-alt me-1"></i><?php echo count($available_timetables); ?> Timetable(s) Available</span>
                    </div>
                </div>
            </div>

            <div class="filter-section">
                <div class="row align-items-center">
                    <div class="col-md-4 mb-2 mb-md-0">
                        <label class="form-label">Filter by Term</label>
                        <select id="filterTerm" class="form-select"><option value="all">All Terms</option><option value="Term 1">Term 1</option><option value="Term 2">Term 2</option></select>
                    </div>
                    <div class="col-md-4 mb-2 mb-md-0">
                        <label class="form-label">Filter by Year</label>
                        <select id="filterYear" class="form-select"><option value="all">All Years</option></select>
                    </div>
                    <div class="col-md-4"><button id="resetFilters" class="btn btn-secondary w-100"><i class="fas fa-undo-alt me-2"></i>Reset Filters</button></div>
                </div>
            </div>

            <div class="row" id="timetablesGrid">
                <?php if (empty($available_timetables)): ?>
                    <div class="col-12"><div class="empty-state"><div class="empty-icon"><i class="fas fa-calendar-times"></i></div><h4>No Timetables Available</h4><p class="text-muted">No timetables have been generated yet. Please check back later.</p></div></div>
                <?php else: ?>
                    <?php foreach ($available_timetables as $timetable): ?>
                        <div class="col-md-6 col-lg-4 mb-4 timetable-item" data-term="<?php echo htmlspecialchars($timetable['term']); ?>" data-year="<?php echo $timetable['year']; ?>">
                            <div class="timetable-card">
                                <div class="timetable-card-header"><i class="fas fa-calendar-alt fa-2x mb-2"></i><h5 class="mb-0"><?php echo htmlspecialchars($timetable['term']); ?></h5><small><?php echo $timetable['year']; ?></small></div>
                                <div class="timetable-card-body"><div class="timetable-icon"><i class="fas fa-file-pdf"></i></div><h6>Academic Timetable</h6><p class="text-muted small">Form 5 & Form 6</p>
                                    <div class="mt-3">
                                        <button class="btn-download" onclick="downloadTimetable('<?php echo htmlspecialchars($timetable['filename']); ?>')"><i class="fas fa-download me-2"></i>Download</button>
                                        <button class="btn-view" onclick="viewTimetable('<?php echo htmlspecialchars($timetable['filename']); ?>')"><i class="fas fa-eye me-2"></i>View</button>
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
                <div class="modal-body p-0" id="modalBody"><div class="text-center p-5"><div class="spinner-border text-primary"></div><p class="mt-3">Loading timetable...</p></div></div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" id="printModalBtn"><i class="fas fa-print me-2"></i>Print / Save as PDF</button></div>
            </div>
        </div>
    </div>

    <?php include '../controller/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function filterTimetables() {
            const term = $('#filterTerm').val(), year = $('#filterYear').val();
            $('.timetable-item').each(function() {
                const show = (term === 'all' || $(this).data('term') === term) && (year === 'all' || $(this).data('year').toString() === year);
                $(this).toggle(show);
            });
        }
        
        $('#filterTerm, #filterYear').on('change', filterTimetables);
        $('#resetFilters').on('click', function() { $('#filterTerm, #filterYear').val('all'); filterTimetables(); });
        
        function downloadTimetable(filename) { window.location.href = 'download_timetable.php?file=' + encodeURIComponent(filename + '.html'); }
        
        function viewTimetable(filename) {
            $('#modalTitle').text(filename.replace(/_/g, ' '));
            $('#modalBody').html('<div class="text-center p-5"><div class="spinner-border text-primary"></div><p>Loading...</p></div>');
            $.ajax({ url: 'view_timetable.php?file=' + encodeURIComponent(filename + '.html'), success: function(r) { $('#modalBody').html(r); }, error: function() { $('#modalBody').html('<div class="alert alert-danger m-3">Error loading timetable.</div>'); } });
            new bootstrap.Modal(document.getElementById('viewTimetableModal')).show();
        }
        
        $('#printModalBtn').on('click', function() {
            const w = window.open('', '_blank');
            w.document.write('<html><head><title>Timetable</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{padding:20px;}</style></head><body>' + $('#modalBody').html() + '</body></html>');
            w.document.close(); w.print();
        });
        
        // Populate year filter
        const years = [...new Set($('.timetable-item').map(function() { return $(this).data('year'); }).get())].sort().reverse();
        years.forEach(y => $('#filterYear').append(`<option value="${y}">${y}</option>`));
    </script>
</body>
</html>