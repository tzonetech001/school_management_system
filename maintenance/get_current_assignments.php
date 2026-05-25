<?php
session_start();
require_once "../controller/db_connect.php";

header("Content-Type: application/json");

if (!isset($_SESSION["admin_id"])) {
    echo json_encode([]);
    exit();
}

if (isset($_GET["student_id"])) {
    $student_id = mysqli_real_escape_string($conn, $_GET["student_id"]);
    
    $sql = "SELECT ma.*, mi.item_code, mi.item_type
            FROM maintenance_assignments ma
            JOIN maintenance_items mi ON ma.item_id = mi.id
            WHERE ma.student_id = $student_id
            AND ma.status = 'active'
            ORDER BY ma.assigned_date DESC";
    
    $result = mysqli_query($conn, $sql);
    $assignments = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $row['assigned_date'] = date('M d, Y', strtotime($row['assigned_date']));
        $assignments[] = $row;
    }
    
    echo json_encode($assignments);
} else {
    echo json_encode([]);
}
?>