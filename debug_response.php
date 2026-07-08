<?php
// Simple debug script to test payment recording
header('Content-Type: text/plain');

echo "=== Testing Payment Recording ===\n\n";

// Simulate AJAX request
$_POST['action'] = 'record_payment';
$_POST['student_id'] = 1;
$_POST['amount'] = 100;
$_POST['payment_date'] = '2026-07-07';
$_POST['payment_method'] = 'cash';
$_POST['reference'] = 'test123';
$_POST['notes'] = 'test';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['admin_id'] = 1; // Set test admin

// Capture output
ob_start();
require_once 'fee/record_payment.php';
$output = ob_get_clean();

echo "=== RAW OUTPUT ===\n";
echo $output . "\n\n";

echo "=== OUTPUT LENGTH ===\n";
echo strlen($output) . " bytes\n\n";

echo "=== FIRST 200 CHARS ===\n";
echo substr($output, 0, 200) . "\n\n";

echo "=== JSON VALID ===\n";
json_decode($output);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✓ Valid JSON\n";
    echo json_encode(json_decode($output), JSON_PRETTY_PRINT) . "\n";
} else {
    echo "✗ Invalid JSON: " . json_last_error_msg() . "\n";
}
?>