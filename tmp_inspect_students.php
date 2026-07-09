<?php
$env = parse_ini_file(__DIR__ . '/.env');
$conn = mysqli_connect($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
mysqli_set_charset($conn, 'utf8mb4');
$res = mysqli_query($conn, "SHOW COLUMNS FROM students");
while ($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . '|' . $row['Type'] . "\n";
}
echo "---\n";
$check = mysqli_query($conn, "SELECT id, status FROM students LIMIT 5");
while ($row = mysqli_fetch_assoc($check)) {
    echo $row['id'] . '|' . $row['status'] . "\n";
}
mysqli_close($conn);
?>
