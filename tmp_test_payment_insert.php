<?php
$env = parse_ini_file(__DIR__ . '/.env');
$conn = mysqli_connect($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
mysqli_set_charset($conn, 'utf8mb4');
$student_id = 1;
$amount = 100.00;
$payment_date = '2026-07-07';
$payment_method = 'cash';
$reference = 'test';
$notes = 'test';
$admin_id = 12;
$school_id = 1;
$insert_sql = "INSERT INTO student_payments (id, student_id, amount, payment_date, payment_method, reference_number, notes, recorded_by, school_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')";
$insert_stmt = mysqli_prepare($conn, $insert_sql);
$bind_payment_id = 999999;
$bind_student_id = $student_id;
$bind_amount = $amount;
$bind_payment_date = $payment_date;
$bind_payment_method = $payment_method;
$bind_reference = $reference;
$bind_notes = $notes;
$bind_admin_id = $admin_id;
$bind_school_id = $school_id;
mysqli_stmt_bind_param($insert_stmt, 'iidssssii', $bind_payment_id, $bind_student_id, $bind_amount, $bind_payment_date, $bind_payment_method, $bind_reference, $bind_notes, $bind_admin_id, $bind_school_id);
$result = mysqli_stmt_execute($insert_stmt);
if ($result) { echo "insert ok\n"; } else { echo "insert failed: " . mysqli_error($conn) . "\n"; }
mysqli_stmt_close($insert_stmt);
mysqli_close($conn);
?>
