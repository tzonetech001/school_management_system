<?php
session_start();
require_once "../controller/db_connect.php";

header("Content-Type: application/json");

if (!isset($_SESSION["admin_id"])) {
    echo json_encode([]);
    exit();
}

if (isset($_GET["q"]) && strlen($_GET["q"]) >= 2) {
    $search = mysqli_real_escape_string($conn, $_GET["q"]);
    
    $sql = "SELECT s.id, s.index_number, s.first_name, s.second_name, s.last_name, 
                   s.combination, s.class, s.sex, s.is_leaver,
                   CONCAT(s.first_name, ' ', COALESCE(s.second_name, ''), ' ', s.last_name) as full_name
            FROM students s
            WHERE s.status = 1
            AND s.is_leaver = 0
            AND (s.first_name LIKE '%$search%' 
                 OR s.last_name LIKE '%$search%' 
                 OR s.index_number LIKE '%$search%'
                 OR CONCAT(s.first_name, ' ', s.last_name) LIKE '%$search%')
            ORDER BY s.index_number ASC
            LIMIT 10";
    
    $result = mysqli_query($conn, $sql);
    $students = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
    
    echo json_encode($students);
} else {
    echo json_encode([]);
}
?>