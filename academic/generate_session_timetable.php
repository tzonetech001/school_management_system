<?php
// generate_session_timetable.php - FIXED
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../controller/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../mhs/login.php');
    exit();
}

$admin_id = intval($_SESSION['admin_id']);

// ===== GET FORM DATA =====
$term = isset($_POST['term']) && trim($_POST['term']) !== '' ? trim($_POST['term']) : 'Term 02';
$term = preg_replace('/\s+/', ' ', $term); // normalize whitespace
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
$start_time = isset($_POST['start_time']) && preg_match('/^\d{2}:\d{2}$/', $_POST['start_time']) ? $_POST['start_time'] : '08:00';
$session_length = isset($_POST['session_length']) ? max(10, min(180, intval($_POST['session_length']))) : 40;
$sessions_per_day = isset($_POST['sessions_per_day']) ? max(1, min(10, intval($_POST['sessions_per_day']))) : 6;
$break_after = isset($_POST['break_after']) ? intval($_POST['break_after']) : 0;
$break_length = isset($_POST['break_length']) ? max(0, min(120, intval($_POST['break_length']))) : 30;
$export_format = isset($_POST['export_format']) ? $_POST['export_format'] : 'excel';
$action = isset($_POST['action']) ? $_POST['action'] : 'download';
$selected_days = isset($_POST['days']) && is_array($_POST['days']) ? $_POST['days'] : [];

$valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$ordered_days = [];
foreach ($valid_days as $day_name) {
    if (in_array($day_name, $selected_days, true)) {
        $ordered_days[] = $day_name;
    }
}

if (empty($ordered_days)) {
    $ordered_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
}
$selected_days = $ordered_days;

// Ensure break placement is valid
if ($break_after < 1 || $break_after > $sessions_per_day) {
    $break_after = 0;
}

// ===== DOCUMENT NAME - USE EXACT TERM =====
$document_name = $term . ' Timetable - ' . $year;
$filename_base = preg_replace('/[^A-Za-z0-9_\-]/', '_', str_replace(' ', '_', $document_name));
$days_joined = implode(', ', $selected_days);

// Optional metadata overrides for regeneration/edit flows
$generated_at_override = array_key_exists('generated_at_override', $_POST) ? $_POST['generated_at_override'] : null;
$use_generated_at = null;
if (array_key_exists('generated_at_override', $_POST)) {
    $raw_generated_at = trim((string) $generated_at_override);
    if ($raw_generated_at === '') {
        $use_generated_at = date('Y-m-d H:i:s');
    } else {
        $use_generated_at = $raw_generated_at;
    }
} else {
    $use_generated_at = date('Y-m-d H:i:s');
}
$use_generated_at = $use_generated_at ?: date('Y-m-d H:i:s');

$generated_by = array_key_exists('generated_by_override', $_POST) && intval($_POST['generated_by_override']) > 0 ? intval($_POST['generated_by_override']) : $admin_id;
$last_updated_by = array_key_exists('last_updated_by_override', $_POST) && intval($_POST['last_updated_by_override']) > 0 ? intval($_POST['last_updated_by_override']) : $admin_id;
$last_updated_at = array_key_exists('last_updated_at_override', $_POST) && !empty($_POST['last_updated_at_override']) ? $_POST['last_updated_at_override'] : date('Y-m-d H:i:s');
$last_updated_at = $last_updated_at ?: date('Y-m-d H:i:s');

// Get school_id
$school_id_query = "SELECT school_id FROM admins WHERE id = $admin_id";
$school_result = mysqli_query($conn, $school_id_query);
$school_data = mysqli_fetch_assoc($school_result);
$school_id = $school_data['school_id'];

// Get combinations
$form5_combinations = [];
$form5_result = mysqli_query($conn, "SELECT DISTINCT combination FROM students WHERE class = 'Form Five' AND school_id = $school_id AND (is_leaver = 0 OR is_leaver IS NULL) AND combination IS NOT NULL AND combination != '' ORDER BY combination");
while ($row = mysqli_fetch_assoc($form5_result)) { $form5_combinations[] = $row['combination']; }
if (empty($form5_combinations)) { $form5_combinations = ['HGE', 'HGL', 'HGK', 'PCM', 'CBG', 'EGM', 'HGM']; }

$form6_combinations = [];
$form6_result = mysqli_query($conn, "SELECT DISTINCT combination FROM students WHERE class = 'Form Six' AND school_id = $school_id AND (is_leaver = 0 OR is_leaver IS NULL) AND combination IS NOT NULL AND combination != '' ORDER BY combination");
while ($row = mysqli_fetch_assoc($form6_result)) { $form6_combinations[] = $row['combination']; }
if (empty($form6_combinations)) { $form6_combinations = ['HGE', 'HGL', 'HGK', 'PCM', 'CBG', 'EGM', 'HGM']; }

$subject_names = [
    // Keep AC and HTM as short codes per request
    'ac' => 'AC', 'htm' => 'HTM', 'his' => 'History', 'geo' => 'Geography',
    'kisw' => 'Kiswahili', 'eng' => 'English', 'b_math' => 'Basic Math', 'adv_m' => 'Advanced Math',
    'eco' => 'Economics', 'fren' => 'French', 'phy' => 'Physics', 'chem' => 'Chemistry',
    'bio' => 'Biology', 'civ' => 'Civics', 'lit' => 'Literature', 'comp' => 'Computer Science'
];

// Combination -> subject codes mapping for Form Five/Form Six
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

// Subject short display labels
$subject_display = [
    'ac' => 'AC', 'htm' => 'HTM', 'his' => 'HIST', 'geo' => 'GEO', 'kisw' => 'KISW',
    'eng' => 'ENG', 'b_math' => 'B/MATH', 'adv_m' => 'ADV/M', 'eco' => 'ECO', 'fren' => 'FREN'
];

// Get teacher assignments
$form5_teachers = [];
$form5_assignments = mysqli_query($conn, "SELECT sta.subject, sta.teacher_id, sta.is_primary, CONCAT(a.first_name, ' ', a.last_name) as teacher_name FROM subject_teacher_assignments sta JOIN admins a ON sta.teacher_id = a.id WHERE sta.form_level = 'Form Five' AND sta.academic_year = $year AND sta.school_id = $school_id");
while ($row = mysqli_fetch_assoc($form5_assignments)) { $form5_teachers[$row['subject']][] = $row; }

$form6_teachers = [];
$form6_assignments = mysqli_query($conn, "SELECT sta.subject, sta.teacher_id, sta.is_primary, CONCAT(a.first_name, ' ', a.last_name) as teacher_name FROM subject_teacher_assignments sta JOIN admins a ON sta.teacher_id = a.id WHERE sta.form_level = 'Form Six' AND sta.academic_year = $year AND sta.school_id = $school_id");
while ($row = mysqli_fetch_assoc($form6_assignments)) { $form6_teachers[$row['subject']][] = $row; }

function addMinutesToTime($time, $minutes) { return date('H:i', strtotime($time) + ($minutes * 60)); }

function calculateScheduleRows($start_time, $session_length, $sessions_per_day, $break_after, $break_length) {
    $rows = [];
    $current_time = $start_time;
    $session_number = 1;
    for ($i = 1; $i <= $sessions_per_day; $i++) {
        $session_end = addMinutesToTime($current_time, $session_length);
        $rows[] = ['type' => 'session', 'number' => $session_number, 'start' => $current_time, 'end' => $session_end, 'label' => 'Session ' . $session_number];
        $current_time = $session_end;
        $session_number++;
        if ($break_after > 0 && $i == $break_after) {
            $break_end = addMinutesToTime($current_time, $break_length);
            $rows[] = ['type' => 'break', 'start' => $current_time, 'end' => $break_end, 'duration' => $break_length, 'label' => 'BREAK'];
            $current_time = $break_end;
        }
    }
    return $rows;
}

$schedule_rows = calculateScheduleRows($start_time, $session_length, $sessions_per_day, $break_after, $break_length);

$school_name = "School Management System";
$school_q = mysqli_query($conn, "SELECT s.school_name FROM admins a JOIN schools s ON a.school_id = s.id WHERE a.id = $admin_id");
if ($row = mysqli_fetch_assoc($school_q)) { $school_name = $row['school_name']; }

// ============================================================================
// COORDINATED TIMETABLE SCHEDULER
// All class timetables are built together so a teacher can NEVER be assigned to
// two classes in the same day/session (hard constraint). For each slot we pick
// a subject whose teacher is actually free, which both prevents clashes and
// fills far more slots than the old "pick subject first, hope teacher is free"
// approach. Randomisation makes each (re)generation differ from the last.
// ============================================================================

// Find a teacher for $subject who is not already teaching in the current
// session. Prefers the class level's primary teacher, then any other level
// teacher, then the shared cross-level pool. Returns null if none are free.
function pickFreeTeacher($subject, $level_teachers, $global_pool, $busy_now) {
    $candidates = [];
    foreach (($level_teachers[$subject] ?? []) as $t) { $candidates[] = $t; }
    foreach (($global_pool[$subject] ?? []) as $t) {
        $dup = false;
        foreach ($candidates as $c) { if ($c['teacher_id'] == $t['teacher_id']) { $dup = true; break; } }
        if (!$dup) $candidates[] = $t;
    }
    usort($candidates, function($a, $b) { return ($b['is_primary'] ?? 0) <=> ($a['is_primary'] ?? 0); });
    foreach ($candidates as $t) {
        if (empty($busy_now[$t['teacher_id']])) return $t;
    }
    return null;
}

// Build the full schedule for every class. Returns [schedule, report, teacher_busy].
function buildCoordinatedSchedule($classes, $selected_days, $schedule_rows, $subject_names, $global_pool) {
    $schedule = [];      // [class_name][day][row_index] => cell
    $report = ['unassigned' => [], 'skipped' => []];
    $teacher_busy = [];  // [teacher_id][day][session_number] => true

    foreach ($selected_days as $day) {
        // Per-class: subjects already used today (to avoid repeating within a day).
        $used = [];
        foreach ($classes as $c) {
            $used[$c['name']] = [];
            if (!isset($schedule[$c['name']])) $schedule[$c['name']] = [];
            $schedule[$c['name']][$day] = [];
        }

        $session_number = 0;
        foreach ($schedule_rows as $row_index => $row) {
            if ($row['type'] !== 'session') {
                foreach ($classes as $c) {
                    $schedule[$c['name']][$day][$row_index] = ['type' => 'break', 'duration' => $row['duration']];
                }
                continue;
            }
            $session_number++;

            // Teachers already teaching in THIS day/session (across all classes).
            $busy_now = [];

            // Rotate which class picks first each session so no class is starved.
            $order = array_keys($classes);
            shuffle($order);

            foreach ($order as $ci) {
                $c = $classes[$ci];
                $cname = $c['name'];

                // Candidate subjects: those not used yet today; if all are used
                // (more sessions than subjects) fall back to the full list.
                $candidates = array_values(array_diff($c['subjects'], $used[$cname]));
                if (empty($candidates)) $candidates = $c['subjects'];
                shuffle($candidates);

                $chosen_subject = null;
                $chosen_teacher = null;

                // Prefer a subject whose teacher is free right now.
                foreach ($candidates as $subj) {
                    $t = pickFreeTeacher($subj, $c['teachers'], $global_pool, $busy_now);
                    if ($t !== null) { $chosen_subject = $subj; $chosen_teacher = $t; break; }
                }

                if ($chosen_subject === null) {
                    // Every candidate's teacher is busy elsewhere this session.
                    // Place a subject but leave it unassigned (never create a clash).
                    $chosen_subject = $candidates[0];
                    $schedule[$cname][$day][$row_index] = [
                        'type' => 'session',
                        'subject' => $chosen_subject,
                        'subject_name' => $subject_names[$chosen_subject] ?? strtoupper($chosen_subject),
                        'teacher_name' => 'Not Assigned'
                    ];
                    $report['unassigned'][] = ['class' => $cname, 'day' => $day, 'session' => $session_number, 'subject' => $chosen_subject];
                } else {
                    $tid = $chosen_teacher['teacher_id'];
                    $busy_now[$tid] = true;
                    $teacher_busy[$tid][$day][$session_number] = true;
                    $schedule[$cname][$day][$row_index] = [
                        'type' => 'session',
                        'subject' => $chosen_subject,
                        'subject_name' => $subject_names[$chosen_subject] ?? strtoupper($chosen_subject),
                        'teacher_name' => $chosen_teacher['teacher_name'],
                        'teacher_id' => $tid
                    ];
                }

                if (!in_array($chosen_subject, $used[$cname])) $used[$cname][] = $chosen_subject;
            }
        }
    }

    return [$schedule, $report, $teacher_busy];
}

// Stable fingerprint of subject+teacher per class/day/slot, used to guarantee a
// regenerated timetable is not identical to the previous one.
function scheduleSignature($schedule) {
    $parts = [];
    ksort($schedule);
    foreach ($schedule as $cname => $days) {
        ksort($days);
        foreach ($days as $day => $rows) {
            ksort($rows);
            foreach ($rows as $ri => $cell) {
                if (($cell['type'] ?? '') !== 'session') continue;
                $parts[] = $cname . '|' . $day . '|' . $ri . '|' . ($cell['subject'] ?? '') . '|' . ($cell['teacher_name'] ?? '');
            }
        }
    }
    return md5(implode("\n", $parts));
}

// Safety check: scan the finished schedule for any teacher assigned to two
// classes in the same day/session. Should always return an empty array.
function verifyNoClashes($schedule, $selected_days, $schedule_rows) {
    $clashes = [];
    foreach ($selected_days as $day) {
        $seen = []; // [row_index][teacher_name] => class_name
        foreach ($schedule as $cname => $days) {
            foreach ($schedule_rows as $ri => $row) {
                if ($row['type'] !== 'session') continue;
                $cell = $days[$day][$ri] ?? null;
                if (!$cell || ($cell['type'] ?? '') !== 'session') continue;
                $tname = $cell['teacher_name'] ?? '';
                if ($tname === '' || $tname === 'Not Assigned') continue;
                if (isset($seen[$ri][$tname])) {
                    $clashes[] = ['day' => $day, 'session_row' => $ri, 'teacher' => $tname, 'classes' => [$seen[$ri][$tname], $cname]];
                } else {
                    $seen[$ri][$tname] = $cname;
                }
            }
        }
    }
    return $clashes;
}

// Render one class's timetable table from its computed day schedule.
function renderClassTable($class_name, $selected_days, $schedule_rows, $day_schedule) {
    $html  = '<div class="timetable-section" style="margin-bottom: 30px;">';
    $html .= '<table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
    $html .= '<tr style="background-color: #2c7a8f; color: white;">';
    $html .= '<td colspan="' . (count($selected_days) + 1) . '" style="text-align: center; font-size: 14px; font-weight: bold; padding: 12px;">' . htmlspecialchars($class_name) . '</td>';
    $html .= '</tr>';
    $html .= '<tr style="background-color: #3B9DB3; color: white;">';
    $html .= '<th style="width: 100px; padding: 10px;">Day / Time</th>';
    foreach ($schedule_rows as $row) {
        if ($row['type'] == 'session') {
            $html .= '<th>' . $row['label'] . '<br><small>' . $row['start'] . ' - ' . $row['end'] . '</small></th>';
        } else {
            $html .= '<th style="background-color: #ff9800;">' . $row['label'] . '<br><small>' . $row['start'] . ' - ' . $row['end'] . '</small></th>';
        }
    }
    $html .= '</tr>';
    foreach ($selected_days as $day) {
        $html .= '<tr>';
        $html .= '<td style="background-color: #e8f4f8; font-weight: bold; padding: 10px;">' . htmlspecialchars($day) . '</td>';
        foreach ($schedule_rows as $row_index => $row) {
            $cell = $day_schedule[$day][$row_index] ?? ['type' => 'session', 'subject_name' => 'TBA', 'teacher_name' => 'Not Assigned'];
            if (($cell['type'] ?? 'session') == 'break') {
                $html .= '<td style="background-color: #ffffcc; text-align: center; vertical-align: middle;"><strong>BREAK</strong><br><small>' . ($cell['duration'] ?? '') . ' min</small></td>';
            } else {
                $html .= '<td style="padding: 8px;"><strong>' . htmlspecialchars($cell['subject_name']) . '</strong><br><small style="color: #666;">' . htmlspecialchars($cell['teacher_name']) . '</small></td>';
            }
        }
        $html .= '</tr>';
    }
    $html .= '</table></div>';
    return $html;
}

$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($document_name) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .school-name { font-size: 20px; font-weight: bold; }
        .document-name { font-size: 16px; font-weight: bold; margin-top: 5px; color: #2c7a8f; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 15px; }
        th { background-color: #3B9DB3; color: white; padding: 8px; border: 1px solid #000; text-align: center; }
        td { border: 1px solid #000; padding: 8px; vertical-align: top; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #999; }
        @media print { th { background-color: #3B9DB3 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="school-name">' . htmlspecialchars($school_name) . '</div>
        <div class="document-name">' . htmlspecialchars($document_name) . '</div>
    </div>';

// Build the list of classes to schedule (Form 5 then Form 6 combinations).
$classes = [];
foreach ($form5_combinations as $combo) {
    $classes[] = [
        'name' => "Form 5 - {$combo}",
        'subjects' => $combination_subjects[$combo] ?? array_keys($subject_names),
        'teachers' => $form5_teachers
    ];
}
foreach ($form6_combinations as $combo) {
    $classes[] = [
        'name' => "Form 6 - {$combo}",
        'subjects' => $combination_subjects[$combo] ?? array_keys($subject_names),
        'teachers' => $form6_teachers
    ];
}

// Merged fallback pool (teachers usable across form levels for the same subject).
$global_teacher_pool = [];
foreach ([$form5_teachers, $form6_teachers] as $pool) {
    foreach ($pool as $subj => $tlist) {
        if (!isset($global_teacher_pool[$subj])) $global_teacher_pool[$subj] = [];
        foreach ($tlist as $t) $global_teacher_pool[$subj][] = $t;
    }
}

// Determine the previous timetable's signature so we can guarantee that a
// regeneration produces a different timetable.
$previous_signature = '';
if (isset($_POST['previous_signature']) && $_POST['previous_signature'] !== '') {
    $previous_signature = (string) $_POST['previous_signature'];
} else {
    $prev_stmt = $conn->prepare("SELECT signature FROM generated_timetables WHERE term = ? AND year = ? AND school_id = ? ORDER BY id DESC LIMIT 1");
    if ($prev_stmt) {
        $prev_stmt->bind_param("sii", $term, $year, $school_id);
        $prev_stmt->execute();
        $prev_row = $prev_stmt->get_result()->fetch_assoc();
        $previous_signature = $prev_row['signature'] ?? '';
        $prev_stmt->close();
    }
}

// Build the schedule, retrying with fresh randomisation until it differs from
// the previous timetable (or we run out of attempts).
$schedule = [];
$report = ['unassigned' => [], 'skipped' => []];
$teacher_busy = [];
$signature = '';
$max_attempts = 10;
$attempt = 0;
for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
    list($schedule, $report, $teacher_busy) = buildCoordinatedSchedule($classes, $selected_days, $schedule_rows, $subject_names, $global_teacher_pool);
    $signature = scheduleSignature($schedule);
    if ($signature !== $previous_signature) break;
}

// Record verification data in the report for inspection.
$report['clashes'] = verifyNoClashes($schedule, $selected_days, $schedule_rows);
$report['attempts'] = $attempt;
$report['signature'] = $signature;
$report['previous_signature'] = $previous_signature;

// Render each class table into the document.
foreach ($classes as $c) {
    $html .= renderClassTable($c['name'], $selected_days, $schedule_rows, $schedule[$c['name']]);
}



$html .= '<div class="footer">Generated on: ' . date('l, F d, Y g:i A') . '<br>© ' . date('Y') . ' ' . htmlspecialchars($school_name) . '</div>';
$html .= '</body></html>';

// ===== SAVE TIMETABLE =====
$timetable_dir = '../uploads/timetables/';
if (!file_exists($timetable_dir)) { mkdir($timetable_dir, 0777, true); }

$filename = $timetable_dir . $filename_base . '.html';
file_put_contents($filename, $html);

// Save scheduling report (skipped/unassigned) as JSON for inspection
$report_filename = $timetable_dir . $filename_base . '_report.json';
file_put_contents($report_filename, json_encode($report, JSON_PRETTY_PRINT));

// ===== SAVE TO DATABASE =====
$delete_sql = "DELETE FROM generated_timetables WHERE term = ? AND year = ? AND school_id = ?";
$delete_stmt = $conn->prepare($delete_sql);
$delete_stmt->bind_param("sii", $term, $year, $school_id);
$delete_stmt->execute();

$insert_sql = "INSERT INTO generated_timetables
               (term, year, filename, document_name, generated_by, generated_at, last_updated_by, last_updated_at, school_id,
                break_after, break_length, start_time, session_length, sessions_per_day, days, signature)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("sissisissiiissss", $term, $year, $filename_base, $document_name, $generated_by, $use_generated_at, $last_updated_by, $last_updated_at, $school_id, $break_after, $break_length, $start_time, $session_length, $sessions_per_day, $days_joined, $signature);
$insert_stmt->execute();

// ===== OUTPUT =====
if ($action === 'download') {
    if ($export_format === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename_base . '.xls"');
        echo $html;
    } elseif ($export_format === 'pdf') {
        $print_btn = '<div style="text-align:center;margin-bottom:20px;"><button onclick="window.print();" style="padding:10px 20px;background:#3B9DB3;color:white;border:none;border-radius:5px;cursor:pointer;">🖨️ Print / Save as PDF</button></div>';
        echo str_replace('<body>', '<body>' . $print_btn, $html);
    } elseif ($export_format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename_base . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, [$school_name]);
        fputcsv($output, [$document_name]);
        fclose($output);
    }
} elseif ($action === 'view') {
    echo $html;
} elseif ($action === 'save') {
    echo '<!DOCTYPE html><html><head><title>Saved</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><meta http-equiv="refresh" content="3;url=session_timetable.php"></head><body><div class="container mt-5"><div class="alert alert-success"><h4 class="alert-heading">Timetable saved!</h4><p><strong>Document:</strong> ' . htmlspecialchars($document_name) . '</p><p><a href="' . $filename . '" target="_blank">Download saved timetable</a></p><hr><p class="mb-0">Redirecting back to timetable creation...</p></div></div></body></html>';
}
?>