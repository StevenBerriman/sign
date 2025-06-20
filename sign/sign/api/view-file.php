<?php
require_once '../config/config.php';

try {
    $db = getDB();
    
    $fileId = $_GET['id'] ?? null;
    
    if (!$fileId) {
        http_response_code(400);
        echo "File ID required";
        exit;
    }
    
    // Get file information from database
    $stmt = $db->prepare('SELECT file_path, file_name, file_type FROM kb_attachments WHERE id = ?');
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        http_response_code(404);
        echo "File not found";
        exit;
    }
    
    $filePath = $file['file_path'];
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo "File not found on disk";
        exit;
    }
    
    $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    // Set appropriate content type
    switch ($fileExt) {
        case 'pdf':
            header('Content-Type: application/pdf');
            break;
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            break;
        case 'png':
            header('Content-Type: image/png');
            break;
        case 'gif':
            header('Content-Type: image/gif');
            break;
        default:
            header('Content-Type: application/octet-stream');
    }
    
    // Set headers for inline viewing
    header('Content-Disposition: inline; filename="' . $file['file_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    
    // Output file content
    readfile($filePath);
    
} catch (Exception $e) {
    http_response_code(500);
    echo "Error viewing file: " . $e->getMessage();
}
?>