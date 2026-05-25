<?php
// process_production.php
session_start();
require_once '../controller/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_id = $_SESSION['admin_id'];
    
    // Handle adding new production
    if (isset($_POST['add_production'])) {
        $category = mysqli_real_escape_string($conn, $_POST['category']);
        $production_type = mysqli_real_escape_string($conn, $_POST['production_type']);
        $quantity = !empty($_POST['quantity']) ? mysqli_real_escape_string($conn, $_POST['quantity']) : NULL;
        $unit = mysqli_real_escape_string($conn, $_POST['unit']);
        $amount = !empty($_POST['amount']) ? mysqli_real_escape_string($conn, $_POST['amount']) : NULL;
        $production_date = mysqli_real_escape_string($conn, $_POST['production_date']);
        $short_note = mysqli_real_escape_string($conn, $_POST['short_note']);
        
        $sql = "INSERT INTO productions (category, production_type, quantity, unit, amount, 
                currency, production_date, short_note, created_by) 
                VALUES ('$category', '$production_type', $quantity, '$unit', $amount, 
                'TZS', '$production_date', '$short_note', $admin_id)";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Production added successfully!";
        } else {
            $_SESSION['error'] = "Error adding production: " . mysqli_error($conn);
        }
    }
    
    // Handle editing production
    elseif (isset($_GET['edit'])) {
        $id = mysqli_real_escape_string($conn, $_GET['edit']);
        $category = mysqli_real_escape_string($conn, $_POST['category']);
        $production_type = mysqli_real_escape_string($conn, $_POST['production_type']);
        $quantity = !empty($_POST['quantity']) ? mysqli_real_escape_string($conn, $_POST['quantity']) : NULL;
        $unit = mysqli_real_escape_string($conn, $_POST['unit']);
        $amount = !empty($_POST['amount']) ? mysqli_real_escape_string($conn, $_POST['amount']) : NULL;
        $production_date = mysqli_real_escape_string($conn, $_POST['production_date']);
        $short_note = mysqli_real_escape_string($conn, $_POST['short_note']);
        
        $sql = "UPDATE productions SET 
                category = '$category',
                production_type = '$production_type',
                quantity = $quantity,
                unit = '$unit',
                amount = $amount,
                production_date = '$production_date',
                short_note = '$short_note'
                WHERE id = $id";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Production updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating production: " . mysqli_error($conn);
        }
    }
    
    // Handle adding uses
    elseif (isset($_POST['add_use'])) {
        $production_id = mysqli_real_escape_string($conn, $_POST['production_id']);
        $use_description = mysqli_real_escape_string($conn, $_POST['use_description']);
        $used_quantity = mysqli_real_escape_string($conn, $_POST['used_quantity']);
        $use_date = mysqli_real_escape_string($conn, $_POST['use_date']);
        $used_by = mysqli_real_escape_string($conn, $_POST['used_by']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);
        
        $sql = "INSERT INTO production_uses (production_id, use_description, used_quantity, 
                use_date, used_by, notes, created_by) 
                VALUES ($production_id, '$use_description', $used_quantity, 
                '$use_date', '$used_by', '$notes', $admin_id)";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Use added successfully!";
        } else {
            $_SESSION['error'] = "Error adding use: " . mysqli_error($conn);
        }
    }
    
    header("Location: productions.php");
    exit();
}
?>