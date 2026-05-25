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
    
    $sql = "SELECT a.id, a.first_name, a.middle_name, a.last_name, a.email, a.sex,
                   CONCAT(a.first_name, ' ', COALESCE(a.middle_name, ''), ' ', a.last_name) as full_name,
                   ar.role_name
            FROM admins a
            LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
            LEFT JOIN admin_roles ar ON ara.role_id = ar.id
            WHERE a.status = 1
            AND (a.first_name LIKE '%$search%' 
                 OR a.last_name LIKE '%$search%' 
                 OR a.email LIKE '%$search%'
                 OR CONCAT(a.first_name, ' ', a.last_name) LIKE '%$search%')
            GROUP BY a.id
            ORDER BY a.first_name
            LIMIT 10";
    
    $result = mysqli_query($conn, $sql);
    $staff = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $staff[] = $row;
    }
    
    echo json_encode($staff);
} else {
    echo json_encode([]);
}
?>