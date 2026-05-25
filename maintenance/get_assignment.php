<?php
session_start();
require_once "../controller/db_connect.php";

header("Content-Type: text/html");

if (!isset($_SESSION["admin_id"])) {
    echo '<div class="alert alert-danger">Access denied</div>';
    exit();
}

if (isset($_GET['id']) && isset($_GET['type'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $type = mysqli_real_escape_string($conn, $_GET['type']);
    
    if ($type == 'student') {
        $sql = "SELECT ma.*, 
               mi.item_code, mi.item_type, mi.description as item_description,
               s.index_number, s.first_name, s.last_name, s.class, s.combination, s.sex,
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               a.first_name as assigned_by_fname, a.last_name as assigned_by_lname,
               CONCAT(a.first_name, ' ', a.last_name) as assigned_by_name
               FROM maintenance_assignments ma
               JOIN maintenance_items mi ON ma.item_id = mi.id
               JOIN students s ON ma.student_id = s.id
               LEFT JOIN admins a ON ma.assigned_by = a.id
               WHERE ma.id = $id";
    } else {
        $sql = "SELECT msa.*, 
               mi.item_code, mi.item_type, mi.description as item_description,
               a.first_name, a.last_name, a.sex, a.email,
               CONCAT(a.first_name, ' ', a.last_name) as staff_name,
               ab.first_name as assigned_by_fname, ab.last_name as assigned_by_lname,
               CONCAT(ab.first_name, ' ', ab.last_name) as assigned_by_name
               FROM maintenance_staff_assignments msa
               JOIN maintenance_items mi ON msa.item_id = mi.id
               JOIN admins a ON msa.staff_id = a.id
               LEFT JOIN admins ab ON msa.assigned_by = ab.id
               WHERE msa.id = $id";
    }
    
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $assignment = mysqli_fetch_assoc($result);
        
        echo '<div class="assignment-details">';
        echo '<h5 class="mb-3 text-primary">Assignment Details</h5>';
        echo '<div class="row">';
        
        if ($type == 'student') {
            echo '<div class="col-md-6 mb-3">';
            echo '<label class="form-label fw-bold">Student Information</label>';
            echo '<div class="p-3 bg-light rounded">';
            echo '<p><strong>Name:</strong> ' . htmlspecialchars($assignment['student_name']) . '</p>';
            echo '<p><strong>Index Number:</strong> ' . htmlspecialchars($assignment['index_number']) . '</p>';
            echo '<p><strong>Class:</strong> ' . htmlspecialchars($assignment['class']) . '</p>';
            echo '<p><strong>Combination:</strong> ' . htmlspecialchars($assignment['combination']) . '</p>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="col-md-6 mb-3">';
            echo '<label class="form-label fw-bold">Staff Information</label>';
            echo '<div class="p-3 bg-light rounded">';
            echo '<p><strong>Name:</strong> ' . htmlspecialchars($assignment['staff_name']) . '</p>';
            echo '<p><strong>Email:</strong> ' . htmlspecialchars($assignment['email']) . '</p>';
            echo '<p><strong>Gender:</strong> ' . htmlspecialchars($assignment['sex']) . '</p>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '<div class="col-md-6 mb-3">';
        echo '<label class="form-label fw-bold">Item Information</label>';
        echo '<div class="p-3 bg-light rounded">';
        echo '<p><strong>Item Code:</strong> <span class="badge bg-primary">' . htmlspecialchars($assignment['item_code']) . '</span></p>';
        echo '<p><strong>Item Type:</strong> <span class="badge bg-info">' . ucfirst($assignment['item_type']) . '</span></p>';
        echo '<p><strong>Description:</strong> ' . htmlspecialchars($assignment['item_description'] ?: 'No description') . '</p>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<div class="row">';
        echo '<div class="col-md-6 mb-3">';
        echo '<label class="form-label fw-bold">Assignment Information</label>';
        echo '<div class="p-3 bg-light rounded">';
        echo '<p><strong>Assigned Date:</strong> ' . date('F j, Y', strtotime($assignment['assigned_date'])) . '</p>';
        if ($assignment['due_date']) {
            echo '<p><strong>Due Date:</strong> ' . date('F j, Y', strtotime($assignment['due_date'])) . '</p>';
        }
        echo '<p><strong>Assigned By:</strong> ' . htmlspecialchars($assignment['assigned_by_name']) . '</p>';
        echo '<p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="col-md-6 mb-3">';
        echo '<label class="form-label fw-bold">Notes</label>';
        echo '<div class="p-3 bg-light rounded">';
        if ($assignment['notes']) {
            echo '<p>' . nl2br(htmlspecialchars($assignment['notes'])) . '</p>';
        } else {
            echo '<p class="text-muted">No notes provided</p>';
        }
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        echo '</div>';
    } else {
        echo '<div class="alert alert-danger">Assignment not found</div>';
    }
} else {
    echo '<div class="alert alert-danger">Invalid request</div>';
}
?>