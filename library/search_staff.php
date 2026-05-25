<?php
session_start();
require_once '../controller/db_connect.php';

header('Content-Type: application/json');

if (isset($_GET['q'])) {
    $search = mysqli_real_escape_string($conn, $_GET['q']);
    $sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, 
                   phone_number, email, status 
            FROM admins 
            WHERE (first_name LIKE '%$search%' OR 
                  last_name LIKE '%$search%' OR 
                  CONCAT(first_name, ' ', last_name) LIKE '%$search%' OR
                  email LIKE '%$search%' OR
                  phone_number LIKE '%$search%') 
            AND status = 1 
            ORDER BY first_name, last_name 
            LIMIT 10";
    
    $result = mysqli_query($conn, $sql);
    $staff = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Count active books
            $staff_id = $row['id'];
            $count_sql = "SELECT COUNT(*) as book_count FROM library_assignments 
                         WHERE user_type = 'staff' AND user_id = $staff_id AND status = 'borrowed'";
            $count_result = mysqli_query($conn, $count_sql);
            $count_data = mysqli_fetch_assoc($count_result);
            $row['active_books'] = $count_data['book_count'] ?? 0;
            
            $staff[] = $row;
        }
    }
    echo json_encode($staff);
} else {
    echo json_encode([]);
}
?>