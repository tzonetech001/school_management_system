<?php
// view_timetable.php - View timetable content
session_start();
require_once '../controller/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../mhs/login.php');
    exit();
}

$file = isset($_GET['file']) ? $_GET['file'] : '';
if (empty($file)) {
    echo '<div class="alert alert-danger">No file specified.</div>';
    exit();
}

$file_path = '../uploads/timetables/' . basename($file);

if (!file_exists($file_path)) {
    echo '<div class="alert alert-danger">File not found.</div>';
    exit();
}

$content = file_get_contents($file_path);
$content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
echo $content;
?>