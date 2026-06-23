<?php
// Enable error reporting for debugging 502 error
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

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
    
    // Query holiday packages
    $stmt = $pdo->prepare("
        SELECT 
            hp.id,
            hp.file_name,
            hp.file_path,
            hp.package_description as description,
            hp.uploaded_at,
            hp.sent_to_app,
            CONCAT(a.first_name, ' ', a.last_name) as uploaded_by_name
        FROM holiday_packages hp
        LEFT JOIN admins a ON hp.uploaded_by = a.id
        WHERE hp.subject = ? AND hp.form_level = ?
        ORDER BY hp.uploaded_at DESC
    ");
    
    $stmt->execute([$subject, $form]);
    $packages = $stmt->fetchAll();
    
    // Format response
    $formatted_packages = [];
    foreach ($packages as $pkg) {
        $ext = pathinfo($pkg['file_name'], PATHINFO_EXTENSION);
        $file_type = strtoupper($ext);
        
        $formatted_packages[] = [
            'id' => $pkg['id'],
            'file_name' => $pkg['file_name'],
            'file_path' => $pkg['file_path'],
            'file_type' => $file_type,
            'description' => $pkg['description'],
            'uploaded_at' => $pkg['uploaded_at'],
            'uploaded_by' => $pkg['uploaded_by_name'],
            'sent_to_app' => (bool)$pkg['sent_to_app'],
            'download_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/school_management_system/' . $pkg['file_path']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'subject' => $subject,
            'form' => $form,
            'count' => count($formatted_packages),
            'packages' => $formatted_packages
        ]
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>