<?php
// candidates/dashboard.php
session_start();
require_once '../controller/db_connect.php'; // Make sure this is before including sidebar

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

// Rest of your code...
?>
<?php include 'header.php'; ?>
<?php include 'sidebar_student.php'; ?>


<?php include '../controller/footer.php'; ?>


</body>
</html>