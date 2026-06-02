<?php
// super/edit_school.php - Process Edit School
session_start();

// Check if Super Admin is logged in
if (!isset($_SESSION['super_admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../controller/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_id = (int)$_POST['school_id'];
    $school_code = mysqli_real_escape_string($conn, trim($_POST['school_code']));
    $school_name = mysqli_real_escape_string($conn, trim($_POST['school_name']));
    $school_motto = mysqli_real_escape_string($conn, trim($_POST['school_motto'] ?? ''));
    $registration_number = mysqli_real_escape_string($conn, trim($_POST['registration_number'] ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Active');
    $subscription_plan = mysqli_real_escape_string($conn, $_POST['subscription_plan'] ?? 'Basic');
    $subscription_expiry = !empty($_POST['subscription_expiry']) ? $_POST['subscription_expiry'] : null;
    $max_students = (int)($_POST['max_students'] ?? 500);
    $max_admins = (int)($_POST['max_admins'] ?? 20);
    $owner_name = mysqli_real_escape_string($conn, trim($_POST['owner_name'] ?? ''));
    $owner_phone = mysqli_real_escape_string($conn, trim($_POST['owner_phone'] ?? ''));
    
    // Check if school code exists for other schools
    $check_sql = "SELECT id FROM schools WHERE school_code = ? AND id != ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $school_code, $school_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        $_SESSION['toast_message'] = "School code already exists for another school!";
        $_SESSION['toast_type'] = "error";
        header("Location: schools.php");
        exit();
    }
    $check_stmt->close();
    
    $update_sql = "UPDATE schools SET 
                   school_code = ?, school_name = ?, school_motto = ?, 
                   registration_number = ?, address = ?, phone = ?, email = ?,
                   status = ?, subscription_plan = ?, subscription_expiry = ?,
                   max_students = ?, max_admins = ?, owner_name = ?, owner_phone = ?
                   WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssssssssssiiss", 
        $school_code, $school_name, $school_motto, $registration_number,
        $address, $phone, $email, $status, $subscription_plan, $subscription_expiry,
        $max_students, $max_admins, $owner_name, $owner_phone, $school_id
    );
    
    if ($stmt->execute()) {
        $_SESSION['toast_message'] = "School '" . $school_name . "' updated successfully!";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Error updating school: " . $conn->error;
        $_SESSION['toast_type'] = "error";
    }
    $stmt->close();
    
    header("Location: schools.php");
    exit();
} else {
    header("Location: schools.php");
    exit();
}
?>