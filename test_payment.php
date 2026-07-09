<?php
// Test script to debug payment recording
session_start();
$_SESSION['admin_id'] = 1;

$ch = curl_init('http://localhost/school_management_system/fee/record_payment.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'action' => 'record_payment',
    'student_id' => 1,
    'amount' => 100,
    'payment_date' => '2026-07-07',
    'payment_method' => 'cash',
    'reference' => 'test123',
    'notes' => 'test payment'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
curl_close($ch);

echo "=== HEADERS ===\n";
echo $headers . "\n\n";
echo "=== BODY ===\n";
echo $body . "\n\n";
echo "=== BODY LENGTH ===\n";
echo strlen($body) . "\n\n";
echo "=== RAW RESPONSE (first 200 chars) ===\n";
echo substr($response, 0, 200) . "\n";
?>