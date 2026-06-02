<?php
// super/add_school.php - Process Add New School
session_start();

// Check if Super Admin is logged in
if (!isset($_SESSION['super_admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../controller/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_code = mysqli_real_escape_string($conn, trim($_POST['school_code']));
    $school_name = mysqli_real_escape_string($conn, trim($_POST['school_name']));
    $school_motto = mysqli_real_escape_string($conn, trim($_POST['school_motto'] ?? ''));
    $registration_number = mysqli_real_escape_string($conn, trim($_POST['registration_number'] ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $subscription_plan = mysqli_real_escape_string($conn, $_POST['subscription_plan'] ?? 'Basic');
    $subscription_expiry = !empty($_POST['subscription_expiry']) ? $_POST['subscription_expiry'] : null;
    $max_students = (int)($_POST['max_students'] ?? 500);
    $max_admins = (int)($_POST['max_admins'] ?? 20);
    $owner_name = mysqli_real_escape_string($conn, trim($_POST['owner_name'] ?? ''));
    $owner_phone = mysqli_real_escape_string($conn, trim($_POST['owner_phone'] ?? ''));
    
    $errors = [];
    
    // Validate
    if (empty($school_code)) $errors[] = "School code is required";
    if (empty($school_name)) $errors[] = "School name is required";
    
    // Check if school code already exists
    $check_sql = "SELECT id FROM schools WHERE school_code = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $school_code);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        $errors[] = "School code already exists. Please use a different code.";
    }
    $check_stmt->close();
    
    if (empty($errors)) {
        $insert_sql = "INSERT INTO schools (school_code, school_name, school_motto, registration_number, 
                       address, phone, email, subscription_plan, subscription_expiry, 
                       max_students, max_admins, owner_name, owner_phone, status, created_by) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sssssssssiissi", 
            $school_code, $school_name, $school_motto, $registration_number,
            $address, $phone, $email, $subscription_plan, $subscription_expiry,
            $max_students, $max_admins, $owner_name, $owner_phone, $_SESSION['super_admin_id']
        );
        
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $_SESSION['toast_message'] = "School '" . $school_name . "' created successfully!";
            $_SESSION['toast_type'] = "success";
            header("Location: schools.php");
            exit();
        } else {
            $_SESSION['toast_message'] = "Error creating school: " . $conn->error;
            $_SESSION['toast_type'] = "error";
            header("Location: schools.php");
            exit();
        }
        $stmt->close();
    } else {
        $_SESSION['toast_message'] = implode(", ", $errors);
        $_SESSION['toast_type'] = "error";
        header("Location: schools.php");
        exit();
    }
} else {
    header("Location: schools.php");
    exit();
}
?>