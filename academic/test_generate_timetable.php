<?php
// test_generate_timetable.php - local test harness for timetable generation
// This script reproduces the core scheduling logic from generate_session_timetable.php
// and runs a sample generation to check for teacher double-booking.

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

function generateClassTimetable($class_name, $selected_days, $schedule_rows, $subject_teachers, $subject_names, $subject_list, &$global_teacher_schedule, &$report, $global_teacher_pool = []) {
    if (empty($subject_list)) {
        $subject_list = array_keys($subject_names);
    }
    $schedule = [];
    foreach ($selected_days as $day) {
        $schedule[$day] = [];
        $session_counter = 1;
        $daily_subjects = $subject_list;
        if (count($daily_subjects) > 1) shuffle($daily_subjects);
        $subject_index = 0;
        $used_subjects = [];
        foreach ($schedule_rows as $row_index => $row) {
            if ($row['type'] == 'session') {
                $available_today = array_values(array_filter($daily_subjects, function($s) use ($used_subjects) { return !in_array($s, $used_subjects); }));
                if (empty($available_today)) {
                    $subject = $daily_subjects[$subject_index % count($daily_subjects)];
                } else {
                    $subject = $available_today[$subject_index % count($available_today)];
                }
                $subject_index++;
                $teachers = $subject_teachers[$subject] ?? [];
                $assigned_teacher_name = 'Not Assigned';
                $assigned_teacher_id = null;
                usort($teachers, function($a, $b) { return ($b['is_primary'] ?? 0) <=> ($a['is_primary'] ?? 0); });
                foreach ($teachers as $t) {
                    $tid = $t['teacher_id'];
                    $slot_key = $tid . '_' . $day . '_' . $session_counter;
                    if (empty($global_teacher_schedule[$slot_key])) {
                        $assigned_teacher_name = $t['teacher_name'];
                        $assigned_teacher_id = $tid;
                        $global_teacher_schedule[$slot_key] = $class_name; // store class for detection
                        break;
                    }
                    $report['skipped'][] = [$tid, $t['teacher_name'], $subject, $class_name, $day, $session_counter];
                }
                if ($assigned_teacher_name === 'Not Assigned' && !empty($global_teacher_pool)) {
                    $fallbacks = $global_teacher_pool[$subject] ?? [];
                    usort($fallbacks, function($a, $b) { return ($b['is_primary'] ?? 0) <=> ($a['is_primary'] ?? 0); });
                    foreach ($fallbacks as $t) {
                        $tid = $t['teacher_id'];
                        $slot_key = $tid . '_' . $day . '_' . $session_counter;
                        if (empty($global_teacher_schedule[$slot_key])) {
                            $assigned_teacher_name = $t['teacher_name'];
                            $assigned_teacher_id = $tid;
                            $global_teacher_schedule[$slot_key] = $class_name;
                            $report['skipped'][] = [$tid, $t['teacher_name'], $subject, $class_name, $day, $session_counter, 'fallback'];
                            break;
                        }
                    }
                }
                if (!in_array($subject, $used_subjects)) $used_subjects[] = $subject;
                $schedule[$day][$row_index] = ['type'=>'session','subject'=>$subject,'subject_name'=>$subject_names[$subject] ?? strtoupper($subject),'teacher_name'=>$assigned_teacher_name];
                if ($assigned_teacher_name === 'Not Assigned') {
                    $report['unassigned'][] = ['class'=>$class_name,'day'=>$day,'session'=>$session_counter,'subject'=>$subject];
                }
                $session_counter++;
            } else {
                $schedule[$day][$row_index] = ['type'=>'break','duration'=>$row['duration']];
            }
        }
    }
    return $schedule; // return data structure for test
}

// Sample config
$selected_days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$schedule_rows = calculateScheduleRows('08:00', 40, 6, 0, 30);
$subject_names = ['ac'=>'AC','htm'=>'HTM','his'=>'HIS','geo'=>'GEO','kisw'=>'KISW','eng'=>'ENG','b_math'=>'B/MATH','adv_m'=>'ADV/M','eco'=>'ECO','fren'=>'FREN'];
$combination_subjects = [
    'HGE'=>['ac','his','geo','b_math','eco'],
    'PCM'=>['ac','phy','chem','adv_m','b_math'],
    'CBG'=>['ac','bio','chem','geo','eco']
];

// Create sample teacher assignments with intentional overlap: teacher 1 teaches AC in multiple combos
$form5_teachers = [
    'ac'=>[['teacher_id'=>1,'is_primary'=>1,'teacher_name'=>'T1 A']],
    'his'=>[['teacher_id'=>2,'is_primary'=>1,'teacher_name'=>'T2 H']],
    'geo'=>[['teacher_id'=>3,'is_primary'=>1,'teacher_name'=>'T3 G']],
    'b_math'=>[['teacher_id'=>4,'is_primary'=>1,'teacher_name'=>'T4 BM']],
    'eco'=>[['teacher_id'=>5,'is_primary'=>1,'teacher_name'=>'T5 E']]
];
$form6_teachers = [
    'ac'=>[['teacher_id'=>1,'is_primary'=>1,'teacher_name'=>'T1 A']],
    'phy'=>[['teacher_id'=>6,'is_primary'=>1,'teacher_name'=>'T6 P']],
    'chem'=>[['teacher_id'=>7,'is_primary'=>1,'teacher_name'=>'T7 C']],
    'adv_m'=>[['teacher_id'=>4,'is_primary'=>1,'teacher_name'=>'T4 BM']]
];

// Build global pool
$global_teacher_pool = [];
foreach ([$form5_teachers,$form6_teachers] as $pool) {
    foreach ($pool as $subj=>$tlist) {
        if (!isset($global_teacher_pool[$subj])) $global_teacher_pool[$subj]=[];
        foreach ($tlist as $t) $global_teacher_pool[$subj][]=$t;
    }
}

$global_teacher_schedule = [];
$report = ['skipped'=>[],'unassigned'=>[]];

$classes = [
    ['name'=>'Form 5 - HGE','subjects'=>$combination_subjects['HGE'],'teachers'=>$form5_teachers],
    ['name'=>'Form 5 - PCM','subjects'=>['ac','b_math','phy','chem'],'teachers'=>array_merge($form5_teachers, $form6_teachers)],
    ['name'=>'Form 6 - CBG','subjects'=>$combination_subjects['CBG'],'teachers'=>$form6_teachers]
];

$assignments = [];
foreach ($classes as $c) {
    $sched = generateClassTimetable($c['name'], $selected_days, $schedule_rows, $c['teachers'], $subject_names, $c['subjects'], $global_teacher_schedule, $report, $global_teacher_pool);
    $assignments[$c['name']] = $sched;
}

// Verify no teacher double-booking: check if any slot key assigned to more than one class
$slot_map = []; // slot_key => class
$conflicts = [];
foreach ($global_teacher_schedule as $slot_key => $class_assigned) {
    // slot_key format tid_day_session
    list($tid,$day,$session) = explode('_', $slot_key);
    $map_key = $tid . '_' . $day . '_' . $session;
    if (!isset($slot_map[$map_key])) $slot_map[$map_key] = $class_assigned;
    else {
        if ($slot_map[$map_key] !== $class_assigned) {
            $conflicts[] = ['teacher_id'=>$tid,'day'=>$day,'session'=>$session,'classes'=>[$slot_map[$map_key], $class_assigned]];
        }
    }
}

// Output summary
echo "Test timetable generation run\n";
echo "Generated classes: " . implode(', ', array_keys($assignments)) . "\n";
if (empty($conflicts)) {
    echo "No teacher double-booking detected.\n";
} else {
    echo "Conflicts detected:\n";
    foreach ($conflicts as $cf) {
        echo "- Teacher {$cf['teacher_id']} booked on {$cf['day']} session {$cf['session']} for classes: " . implode(' vs ', $cf['classes']) . "\n";
    }
}

// Also print unassigned slots
if (!empty($report['unassigned'])) {
    echo "Unassigned slots: " . count($report['unassigned']) . "\n";
    foreach ($report['unassigned'] as $u) {
        echo "- {$u['class']} {$u['day']} session {$u['session']} subject {$u['subject']}\n";
    }
} else {
    echo "All slots assigned teachers.\n";
}

?>