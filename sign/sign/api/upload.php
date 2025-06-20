<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

require_once '../config/config.php';

try {
    $db = getDB();
    
    // Simple authentication check
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $current_user = ['id' => 1, 'role' => 'admin'];
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    $uploadDir = '../uploads/quotes/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $response = ['success' => false];
    
    // Handle quote file upload
    if (isset($_FILES['quoteFile'])) {
        $file = $_FILES['quoteFile'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
            
            if (!in_array($fileExt, $allowedTypes)) {
                throw new Exception('File type not allowed. Please upload PDF, images, or Word documents.');
            }
            
            $newFileName = 'quote_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
            $filePath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                
                // Create file record for tracking
                $stmt = $db->prepare('INSERT INTO kb_attachments (contract_id, file_type, file_name, file_path, file_size) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([
                    null, // No contract yet
                    'quote',
                    $file['name'],
                    $filePath,
                    $file['size']
                ]);
                
                $file_id = $db->lastInsertId();
                
                // Create empty quote record for manual data entry
                $stmt = $db->prepare('
                    INSERT INTO kb_quotes 
                    (file_path, original_filename, client_name, client_email, client_address, client_phone, quote_number, line_items_json, total_amount, extraction_method, requires_manual_entry) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                
                $stmt->execute([
                    $filePath,
                    $file['name'],
                    '', // Empty - to be filled manually
                    '',
                    '',
                    '',
                    'Q-' . date('Y') . '-' . rand(100, 999),
                    json_encode([]), // Empty line items
                    0,
                    'manual_entry',
                    1 // Requires manual entry
                ]);
                
                $quote_id = $db->lastInsertId();
                
                $response = [
                    'success' => true,
                    'message' => 'File uploaded successfully - ready for manual data entry',
                    'fileName' => $newFileName,
                    'fileId' => $file_id,
                    'quoteId' => $quote_id,
                    'fileInfo' => [
                        'originalName' => $file['name'],
                        'fileSize' => $file['size'],
                        'fileType' => $fileExt,
                        'uploadPath' => $filePath,
                        'viewUrl' => '/sign/api/view-file.php?id=' . $file_id
                    ],
                    'extractedData' => [
                        'clientName' => '',
                        'clientEmail' => '',
                        'clientAddress' => '',
                        'clientPhone' => '',
                        'quoteNumber' => 'Q-' . date('Y') . '-' . rand(100, 999),
                        'lineItems' => [],
                        'items' => [], // For React compatibility
                        'totalAmount' => 0,
                        'requiresManualEntry' => true
                    ]
                ];
            } else {
                throw new Exception('Failed to save uploaded file');
            }
        } else {
            throw new Exception('File upload error: ' . $file['error']);
        }
    }
    
    // Handle drawing files
    $drawingFiles = [];
    for ($i = 0; $i < 5; $i++) {
        if (isset($_FILES["drawingFile$i"])) {
            $file = $_FILES["drawingFile$i"];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $newFileName = 'drawing_' . time() . '_' . $i . '.' . $fileExt;
                $filePath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $stmt = $db->prepare('INSERT INTO kb_attachments (contract_id, file_type, file_name, file_path, file_size) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([
                        null,
                        'drawing',
                        $file['name'],
                        $filePath,
                        $file['size']
                    ]);
                    
                    $drawingFiles[] = [
                        'name' => $file['name'],
                        'fileName' => $newFileName,
                        'id' => $db->lastInsertId()
                    ];
                }
            }
        }
    }
    
    if (!empty($drawingFiles)) {
        $response['drawingFiles'] = $drawingFiles;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Upload error: ' . $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
?>