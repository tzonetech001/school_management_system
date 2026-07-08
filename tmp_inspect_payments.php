<?php
$env = parse_ini_file(__DIR__ . '/.env');
$conn = mysqli_connect($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if (!$conn) { die("db fail\n"); }
mysqli_set_charset($conn, 'utf8mb4');
foreach (['student_payments', 'fee_settings'] as $t) {
    echo "TABLE $t\n";
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    echo 'exists=' . ($res && mysqli_num_rows($res) ? 1 : 0) . "\n";
    if ($res && mysqli_num_rows($res)) {
        $cols = mysqli_query($conn, "SHOW COLUMNS FROM `$t`");
        while ($row = mysqli_fetch_assoc($cols)) {
            echo $row['Field'] . '|' . $row['Type'] . "\n";
        }
    }
    echo "---\n";
}
mysqli_close($conn);
?>
