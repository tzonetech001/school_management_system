<?php
session_start();
require_once '../controller/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode([]);
    exit();
}

$game_type_id = isset($_GET['game_type_id']) ? intval($_GET['game_type_id']) : 0;

if ($game_type_id > 0) {
    $sql = "SELECT id, tournament_name, season FROM tournaments 
            WHERE game_type_id = ? AND is_archived = FALSE 
            ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $game_type_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tournaments = [];
    while ($row = $result->fetch_assoc()) {
        $tournaments[] = $row;
    }
    echo json_encode($tournaments);
} else {
    echo json_encode([]);
}
?>