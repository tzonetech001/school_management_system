<?php
session_start();
require_once '../controller/db_connect.php';

header('Content-Type: application/json');

$sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, 
               phone_number, email 
        FROM admins 
        WHERE status = 1 
        ORDER BY first_name, last_name";
    
$result = mysqli_query($conn, $sql);
$staff = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Count active assignments for this staff
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
?>