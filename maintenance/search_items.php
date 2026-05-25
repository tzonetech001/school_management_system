<?php
session_start();
require_once "../controller/db_connect.php";

header("Content-Type: application/json");

if (!isset($_SESSION["admin_id"])) {
    echo json_encode([]);
    exit();
}

if (isset($_GET["q"]) && isset($_GET["type"])) {
    $search = mysqli_real_escape_string($conn, $_GET["q"]);
    $type = mysqli_real_escape_string($conn, $_GET["type"]);
    
    $sql = "SELECT id, item_code, item_type, description, status
            FROM maintenance_items 
            WHERE item_type = '$type'
            AND status = 'available'
            AND (item_code LIKE '%$search%' 
                 OR description LIKE '%$search%')
            ORDER BY item_code
            LIMIT 10";
    
    $result = mysqli_query($conn, $sql);
    $items = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    
    echo json_encode($items);
} else {
    echo json_encode([]);
}
?>