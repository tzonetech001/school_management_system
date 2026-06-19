<?php
// download_timetable.php - Download timetable file
session_start();
require_once '../controller/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../mhs/login.php');
    exit();
}

$file = isset($_GET['file']) ? $_GET['file'] : '';
if (empty($file)) {
    header('Location: teacher_timetable.php');
    exit();
}

$file_path = '../uploads/timetables/' . basename($file);

if (!file_exists($file_path)) {
    die('File not found.');
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: must-revalidate');
header('Pragma: public');

ob_clean();
flush();
readfile($file_path);
exit();
?>