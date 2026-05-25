<?php
// subject_entry_six.php - Form six results entry for assigned teachers
session_start();
require_once '../controller/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check login
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../mhs/login.php");
    exit();
}

$admin_id = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;
$current_year = date('Y');
$current_subject = isset($_GET['subject']) ? $_GET['subject'] : '';
$current_exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Redirect if no subject selected
if (empty($current_subject)) {
    header("Location: subject_entry.php");
    exit();
}

// Get teacher's name
$teacher_sql = "SELECT CONCAT(first_name, ' ', last_name) as teacher_name FROM admins WHERE id = $admin_id";
$teacher_result = mysqli_query($conn, $teacher_sql);
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_name = $teacher['teacher_name'] ?? 'Teacher';

// Verify teacher has permission for this subject in Form six
$permission_check = "SELECT sta.*, 
                    CONCAT(a.first_name, ' ', a.last_name) as teacher_name
                    FROM subject_teacher_assignments sta
                    JOIN admins a ON sta.teacher_id = a.id
                    WHERE sta.teacher_id = $admin_id 
                    AND sta.subject = '$current_subject'
                    AND sta.form_level = 'Form six'
                    AND sta.academic_year = $current_year
                    AND sta.can_enter_results = 1";
$permission_result = mysqli_query($conn, $permission_check);
$current_assignment = mysqli_fetch_assoc($permission_result);

if (!$current_assignment) {
    $_SESSION['error'] = "You don't have permission to enter results for this subject in Form six.";
    header("Location: subject_entry.php");
    exit();
}

// Get ACTIVE exam types for Form six
$exam_types_sql = "SELECT * FROM exam_types 
                   WHERE form_level = 'Form six' 
                   AND is_active = 1
                   ORDER BY year DESC, id DESC";
$exam_types_result = mysqli_query($conn, $exam_types_sql);
$exam_types = [];
while ($row = mysqli_fetch_assoc($exam_types_result)) {
    $exam_types[] = $row;
}

// If no active exam selected, get the most recent ACTIVE one
if ($current_exam_id == 0 && count($exam_types) > 0) {
    $current_exam_id = $exam_types[0]['id'];
}

// Get current exam details
$current_exam = null;
if ($current_exam_id > 0) {
    $exam_sql = "SELECT * FROM exam_types WHERE id = $current_exam_id AND is_active = 1";
    $exam_result = mysqli_query($conn, $exam_sql);
    $current_exam = mysqli_fetch_assoc($exam_result);
    
    if (!$current_exam && count($exam_types) > 0) {
        $current_exam_id = $exam_types[0]['id'];
        $exam_sql = "SELECT * FROM exam_types WHERE id = $current_exam_id";
        $exam_result = mysqli_query($conn, $exam_sql);
        $current_exam = mysqli_fetch_assoc($exam_result);
    }
}

// Subject display names
$subject_display = [
    'ac' => 'AC (Academic Communication)',
    'htm' => 'HTM (Historia ya Tanzania na Maadili)',
    'his' => 'HIST (History)',
    'geo' => 'GEO (Geography)',
    'kisw' => 'KISW (Kiswahili)',
    'eng' => 'ENG (English)',
    'b_math' => 'B/MATH (Basic Mathematics)',
    'adv_m' => 'ADV/M (Advanced Mathematics)',
    'eco' => 'ECO (Economics)',
    'fren' => 'FREN (French)'
];

// Combination subjects mapping for Form six
$combination_subjects = [
    'HGE' => ['ac', 'htm', 'his', 'geo', 'b_math', 'eco'],
    'HGL' => ['ac', 'htm', 'his', 'geo', 'eng'],
    'HGK' => ['ac', 'htm', 'his', 'geo', 'kisw'],
    'HKL' => ['ac', 'htm', 'his', 'kisw', 'eng'],
    'KLF' => ['ac', 'htm', 'kisw', 'eng', 'fren'],
    'EGM' => ['ac', 'htm', 'geo', 'adv_m', 'eco'],
    'HLF' => ['ac', 'htm', 'his', 'eng', 'fren'],
    'HGF' => ['ac', 'htm', 'his', 'geo', 'fren']
];

// Get all Form six students
$students_sql = "SELECT s.id, s.index_number, s.first_name, s.last_name, s.second_name, s.sex, s.combination,
                    fr.$current_subject as marks
                FROM students s
                LEFT JOIN form_six_results fr ON s.id = fr.student_id AND fr.exam_type_id = $current_exam_id
                WHERE s.class = 'Form six' AND (s.is_leaver IS NULL OR s.is_leaver = FALSE)
                ORDER BY 
                    FIELD(s.combination, 'HGE', 'HGL', 'HGK', 'HKL', 'KLF', 'EGM', 'HLF', 'HGF'),
                    CASE WHEN s.sex = 'Female' THEN 1 ELSE 2 END,
                    s.first_name, s.last_name";

$students_result = mysqli_query($conn, $students_sql);
$students = [];
while ($row = mysqli_fetch_assoc($students_result)) {
    $students[] = $row;
}

// Show message if no active exam exists
if (empty($exam_types)) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>No Active Exam - Form six</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
            .main-content { margin-left: 260px; padding: 20px; }
            @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } }
        </style>
    </head>
    <body>
        <?php include '../controller/header.php'; ?>
        <?php include '../controller/sidebar.php'; ?>
        <div class="main-content">
            <div class="container-fluid">
                <div class="alert alert-warning text-center py-5">
                    <i class="fas fa-calendar-times fa-3x mb-3 d-block"></i>
                    <h4>No Active Exam Available</h4>
                    <p>There is no active exam for Form six at the moment.</p>
                    <p>You have been assigned to <strong><?php echo $subject_display[$current_subject]; ?></strong> but no exam is currently active.</p>
                    <hr>
                    <a href="subject_entry.php" class="btn btn-primary">← Back to Subject Selection</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ========== AJAX HANDLER ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    
    $student_id = intval($_POST['student_id']);
    $exam_type_id = intval($_POST['exam_type_id']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $marks = ($_POST['marks'] !== '' && $_POST['marks'] !== null) ? intval($_POST['marks']) : null;
    
    // Verify permission again
    $permission_check = "SELECT id FROM subject_teacher_assignments 
                         WHERE teacher_id = $admin_id AND subject = '$subject' 
                         AND form_level = 'Form six' AND academic_year = $current_year
                         AND can_enter_results = 1";
    $permission_result = mysqli_query($conn, $permission_check);
    
    if (mysqli_num_rows($permission_result) == 0) {
        echo json_encode(['success' => false, 'error' => 'Permission denied.']);
        exit();
    }
    
    if ($marks !== null && ($marks < 0 || $marks > 100)) {
        echo json_encode(['success' => false, 'error' => 'Marks must be between 0 and 100']);
        exit();
    }
    
    $check_sql = "SELECT id FROM form_six_results WHERE student_id = $student_id AND exam_type_id = $exam_type_id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        if ($marks !== null) {
            $update_sql = "UPDATE form_six_results SET `$subject` = $marks, updated_at = CURRENT_TIMESTAMP 
                          WHERE student_id = $student_id AND exam_type_id = $exam_type_id";
        } else {
            $update_sql = "UPDATE form_six_results SET `$subject` = NULL, updated_at = CURRENT_TIMESTAMP 
                          WHERE student_id = $student_id AND exam_type_id = $exam_type_id";
        }
        mysqli_query($conn, $update_sql);
    } else {
        if ($marks !== null) {
            $insert_sql = "INSERT INTO form_six_results (student_id, exam_type_id, `$subject`, entered_by, entered_at) 
                          VALUES ($student_id, $exam_type_id, $marks, $admin_id, NOW())";
        } else {
            $insert_sql = "INSERT INTO form_six_results (student_id, exam_type_id, entered_by, entered_at) 
                          VALUES ($student_id, $exam_type_id, $admin_id, NOW())";
        }
        mysqli_query($conn, $insert_sql);
    }
    
    echo json_encode(['success' => true]);
    exit();
}

function getGradeLetter($marks) {
    if ($marks >= 80) return 'A';
    if ($marks >= 70) return 'B';
    if ($marks >= 60) return 'C';
    if ($marks >= 50) return 'D';
    if ($marks >= 40) return 'E';
    if ($marks >= 35) return 'S';
    return 'F';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $subject_display[$current_subject]; ?> - Form six Results Entry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
       
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } }
        .results-table-container { overflow-x: auto; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .results-table { min-width: 700px; font-size: 13px; }
        .results-table thead th { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; padding: 12px 8px; text-align: center; position: sticky; top: 0; }
        .results-table tbody td { padding: 10px 8px; text-align: center; vertical-align: middle; border-bottom: 1px solid #e9ecef; }
        .results-table tbody tr:hover { background-color: #f8f9fa; }
        .results-table tbody tr.active-row { background-color: #fff3cd; }
        .subject-input { width: 80px; text-align: center; padding: 6px 4px; border: 1px solid #ddd; border-radius: 6px; }
        .subject-input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(44,62,80,0.1); }
        .subject-input.saving { background-color: #fff3cd; border-color: var(--warning-color); }
        .subject-input.saved { background-color: #d4edda; border-color: var(--success-color); }
        .subject-input.max-score { border: 2px solid #dc3545; background-color: #fff5f5; }
        .badge-grade { padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .grade-A { background: #27ae60; color: white; }
        .grade-B { background: #2ecc71; color: white; }
        .grade-C { background: #f39c12; color: white; }
        .grade-D { background: #e67e22; color: white; }
        .grade-E { background: #95a5a6; color: white; }
        .grade-S { background: #7f8c8d; color: white; }
        .grade-F { background: #e74c3c; color: white; }
        .exam-selector { background: white; border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .auto-save-indicator { position: fixed; bottom: 20px; right: 20px; background: var(--success-color); color: white; padding: 8px 16px; border-radius: 30px; font-size: 12px; z-index: 1000; display: none; align-items: center; gap: 8px; }
        .auto-save-indicator.show { display: flex; }
        .auto-save-indicator.error { background: var(--danger-color); }
        .loading-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .teacher-info-card {background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; border-radius: 12px; padding: 12px 20px; margin-bottom: 20px; }
        @media (max-width: 768px) { .subject-input { width: 60px; } }
    </style>
</head>
<body>
    <?php include '../controller/header.php'; ?>
    <?php include '../controller/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Teacher Info Card -->
            <div class="teacher-info-card">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <i class="fas fa-chalkboard-teacher me-2"></i>
                        <strong><?php echo htmlspecialchars($teacher_name); ?></strong>
                        <?php if ($current_assignment['is_primary']): ?>
                            <span class="ms-2"><i class="fas fa-star"></i> Primary Teacher</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <i class="fas fa-calendar-alt me-1"></i> Year: <?php echo $current_year; ?>
                        <i class="fas fa-graduation-cap ms-3 me-1"></i> Form six
                    </div>
                </div>
            </div>

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h2>
                    <i class="fas fa-book me-2"></i>
                    <?php echo $subject_display[$current_subject]; ?> - Form six Results Entry
                </h2>
                <a href="subject_entry.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Subjects
                </a>
            </div>

            <!-- Exam Selector -->
            <div class="exam-selector">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <label class="fw-bold mb-2">Select Exam:</label>
                        <select id="examSelector" class="form-select w-auto d-inline-block ms-2" style="width: auto;">
                            <?php foreach ($exam_types as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>" <?php echo $current_exam_id == $exam['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exam['exam_name']); ?> (<?php echo $exam['year']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="text-muted me-3"><i class="fas fa-save me-1"></i> Auto-save</span>
                        <span class="text-muted"><i class="fas fa-arrow-pointer me-1"></i> Arrow keys to navigate</span>
                    </div>
                </div>
            </div>

            <!-- Results Table -->
            <div class="results-table-container">
                <table class="table results-table">
                    <thead>
                        <tr><th>#</th><th>Index No.</th><th>Student Name</th><th>Sex</th><th>Comb</th><th>Marks</th><th>Grade</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="7" class="text-center py-5">No Form six students found.</td></tr>
                        <?php else: 
                            $sn = 1;
                            foreach ($students as $student): 
                                $combination = $student['combination'];
                                $takes_subject = isset($combination_subjects[$combination]) && in_array($current_subject, $combination_subjects[$combination]);
                                if (!$takes_subject) continue;
                        ?>
                            <tr data-student-id="<?php echo $student['id']; ?>">
                                <td><?php echo $sn++; ?></td>
                                <td><strong><?php echo htmlspecialchars($student['index_number']); ?></strong></td>
                                <td class="text-start"><strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong></td>
                                <td><span class="badge <?php echo $student['sex'] == 'Male' ? 'bg-primary' : 'bg-danger'; ?>"><?php echo $student['sex']; ?></span></td>
                                <td><span class="badge bg-info"><?php echo $combination; ?></span></td>
                                <td><input type="number" class="subject-input" data-student-id="<?php echo $student['id']; ?>" value="<?php echo $student['marks'] !== null ? $student['marks'] : ''; ?>" min="0" max="100" placeholder="-"></td>
                                <td class="grade-cell"><?php if ($student['marks'] !== null): ?><span class="badge-grade grade-<?php echo getGradeLetter($student['marks']); ?>"><?php echo getGradeLetter($student['marks']); ?></span><?php else: ?>-<?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Note:</strong> Marks are automatically saved after 1.5 seconds. Use ↑ ↓ ← → arrow keys to navigate between cells.
            </div>
        </div>
    </div>

    <div id="autoSaveIndicator" class="auto-save-indicator"><div class="loading-spinner"></div><span>Saving...</span></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let saveTimeouts = {};
        let currentRow = 0, currentCol = 0, allInputs = [];

        function getAllInputs() {
            const inputs = [];
            $('.results-table tbody tr').each(function(rowIndex) {
                $(this).find('.subject-input').each(function(colIndex) {
                    inputs.push({ element: this, row: rowIndex, col: colIndex, studentId: $(this).data('student-id') });
                });
            });
            return inputs;
        }

        function showAutoSaveIndicator(message, isError) {
            const indicator = $('#autoSaveIndicator');
            $('#autoSaveIndicator span').text(message);
            if (isError) indicator.addClass('error'); else indicator.removeClass('error');
            indicator.addClass('show');
            setTimeout(() => indicator.removeClass('show'), 2000);
        }

        function updateGradeDisplay(input, marks) {
            const gradeCell = $(input).closest('tr').find('.grade-cell');
            const marksNum = parseInt(marks);
            if (!isNaN(marksNum) && marksNum > 0) {
                let grade = marksNum >= 80 ? 'A' : marksNum >= 70 ? 'B' : marksNum >= 60 ? 'C' : marksNum >= 50 ? 'D' : marksNum >= 40 ? 'E' : marksNum >= 35 ? 'S' : 'F';
                let gradeClass = `grade-${grade}`;
                gradeCell.html(`<span class="badge-grade ${gradeClass}">${grade}</span>`);
            } else {
                gradeCell.html('-');
            }
        }

        function performSave(studentId, marks) {
            const input = $(`input[data-student-id="${studentId}"]`);
            input.addClass('saving');
            $.ajax({
                url: window.location.href, method: 'POST', data: { ajax_save: 1, student_id: studentId, exam_type_id: <?php echo $current_exam_id; ?>, subject: '<?php echo $current_subject; ?>', marks: marks },
                success: function(response) {
                    input.removeClass('saving');
                    if (response.success) {
                        input.addClass('saved');
                        setTimeout(() => input.removeClass('saved'), 1000);
                        showAutoSaveIndicator('Saved!', false);
                    } else { showAutoSaveIndicator('Error!', true); }
                }, error: function() { input.removeClass('saving'); showAutoSaveIndicator('Error!', true); }
            });
        }

        function autoSave(studentId, marks) {
            if (saveTimeouts[studentId]) clearTimeout(saveTimeouts[studentId]);
            saveTimeouts[studentId] = setTimeout(() => { performSave(studentId, marks); delete saveTimeouts[studentId]; }, 1500);
        }

        function focusCurrentCell() {
            allInputs = getAllInputs();
            const current = allInputs.find(i => i.row === currentRow && i.col === currentCol);
            if (current) { current.element.focus(); $(current.element).closest('tr').addClass('active-row'); }
        }

        $(document).ready(function() {
            allInputs = getAllInputs();
            $('.subject-input').on('input', function() {
                let marks = $(this).val();
                if (marks !== '') { marks = parseInt(marks); if (marks < 0) marks = 0; if (marks > 100) marks = 100; $(this).val(marks); }
                if (marks === 100) $(this).addClass('max-score'); else $(this).removeClass('max-score');
                updateGradeDisplay(this, marks);
                autoSave($(this).data('student-id'), marks);
            });
            $(document).on('keydown', function(e) {
                if (!$(':focus').is('.subject-input')) return;
                const idx = allInputs.findIndex(i => i.element === document.activeElement);
                if (idx === -1) return;
                if (e.key === 'ArrowRight' && idx + 1 < allInputs.length) { e.preventDefault(); currentRow = allInputs[idx+1].row; currentCol = allInputs[idx+1].col; focusCurrentCell(); }
                if (e.key === 'ArrowLeft' && idx > 0) { e.preventDefault(); currentRow = allInputs[idx-1].row; currentCol = allInputs[idx-1].col; focusCurrentCell(); }
                if (e.key === 'ArrowDown') { e.preventDefault(); const next = allInputs.find(i => i.row > currentRow && i.col === currentCol); if(next) { currentRow = next.row; currentCol = next.col; focusCurrentCell(); } }
                if (e.key === 'ArrowUp') { e.preventDefault(); const prev = [...allInputs].reverse().find(i => i.row < currentRow && i.col === currentCol); if(prev) { currentRow = prev.row; currentCol = prev.col; focusCurrentCell(); } }
            });
            $('#examSelector').on('change', function() { window.location.href = `subject_entry_six.php?subject=<?php echo urlencode($current_subject); ?>&exam_id=${$(this).val()}`; });
            setTimeout(() => { $('.subject-input').first().focus(); }, 500);
        });
    </script>
</body>
</html>
<?php include '../controller/footer.php'; ?>