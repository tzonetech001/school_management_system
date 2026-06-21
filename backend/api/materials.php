<?php
// API endpoint to get shareable learning materials
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = getDBConnection();
    
    // Get parameters
    $subject = isset($_GET['subject']) ? $_GET['subject'] : '';
    $form = isset($_GET['form']) ? $_GET['form'] : '';
    
    if (empty($subject) || empty($form)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Subject and form are required']);
        exit;
    }
    
    // Query materials
    $stmt = $pdo->prepare("
        SELECT 
            lm.id,
            lm.file_name,
            lm.file_path,
            lm.file_type,
            lm.description,
            lm.uploaded_at,
            CONCAT(a.first_name, ' ', a.last_name) as uploaded_by_name
        FROM learning_materials lm
        LEFT JOIN admins a ON lm.uploaded_by = a.id
        WHERE lm.subject = ? AND lm.form_level = ?
        ORDER BY lm.uploaded_at DESC
    ");
    
    $stmt->execute([$subject, $form]);
    $materials = $stmt->fetchAll();
    
    // Format response
    $formatted_materials = [];
    foreach ($materials as $mat) {
        $ext = pathinfo($mat['file_name'], PATHINFO_EXTENSION);
        $file_type = strtoupper($ext);
        
        $formatted_materials[] = [
            'id' => $mat['id'],
            'file_name' => $mat['file_name'],
            'file_path' => $mat['file_path'],
            'file_type' => $file_type,
            'description' => $mat['description'],
            'uploaded_at' => $mat['uploaded_at'],
            'uploaded_by' => $mat['uploaded_by_name'],
            'download_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/school_management_system/' . $mat['file_path']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'subject' => $subject,
            'form' => $form,
            'count' => count($formatted_materials),
            'materials' => $formatted_materials
        ]
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>