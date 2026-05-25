<?php
// get_available_productions.php
session_start();
require_once '../controller/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode([]);
    exit();
}

// Get productions with available quantity
$sql = "SELECT p.id, p.production_type, p.unit, p.quantity,
        (p.quantity - COALESCE(SUM(pu.used_quantity), 0)) as available
        FROM productions p
        LEFT JOIN production_uses pu ON p.id = pu.production_id
        WHERE p.is_active = 1 AND p.quantity IS NOT NULL
        GROUP BY p.id
        HAVING available > 0
        ORDER BY p.production_date DESC";

$result = mysqli_query($conn, $sql);
$productions = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $productions[] = $row;
    }
}

echo json_encode($productions);
?>