<?php
// Contract Documents API - Handle document uploads and downloads
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

require_once 'db-config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Create documents table if it doesn't exist
$createTable = "
CREATE TABLE IF NOT EXISTS kb_contract_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100),
    document_type ENUM('plan', 'invoice', 'receipt', 'specification', 'warranty', 'other') DEFAULT 'other',
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by VARCHAR(100),
    description TEXT,
    is_visible_to_client BOOLEAN DEFAULT TRUE,
    INDEX idx_contract_id (contract_id)
)";
$pdo->exec($createTable);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    
    // Handle file upload
    if (!isset($_FILES['document']) || !isset($_POST['contract_id'])) {
        echo json_encode(['success' => false, 'error' => 'Missing file or contract ID']);
        exit();
    }

    $contractId = (int)$_POST['contract_id'];
    $file = $_FILES['document'];
    $documentType = $_POST['document_type'] ?? 'other';
    $description = $_POST['description'] ?? '';
    $documentName = $_POST['document_name'] ?? pathinfo($file['name'], PATHINFO_FILENAME);

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'File upload error']);
        exit();
    }

    // Check file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'File too large (max 10MB)']);
        exit();
    }

    // Create upload directory
    $uploadDir = 'uploads/contracts/' . $contractId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $file['name']);
    $filePath = $uploadDir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        // Save to database
        $stmt = $pdo->prepare("
            INSERT INTO kb_contract_documents 
            (contract_id, document_name, original_filename, file_path, file_size, mime_type, document_type, description, uploaded_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $contractId,
            $documentName,
            $file['name'],
            $filePath,
            $file['size'],
            $file['type'],
            $documentType,
            $description,
            'admin'
        ]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'document_id' => $pdo->lastInsertId()
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database save failed']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'File upload failed']);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'download') {
    
    // Handle file download
    if (!isset($_GET['id'])) {
        echo json_encode(['success' => false, 'error' => 'Document ID required']);
        exit();
    }

    $documentId = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM kb_contract_documents WHERE id = ? AND is_visible_to_client = 1");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document || !file_exists($document['file_path'])) {
        echo json_encode(['success' => false, 'error' => 'Document not found']);
        exit();
    }

    // Set headers for file download
    header('Content-Type: ' . $document['mime_type']);
    header('Content-Disposition: attachment; filename="' . $document['original_filename'] . '"');
    header('Content-Length: ' . $document['file_size']);
    header('Cache-Control: must-revalidate');

    // Output file
    readfile($document['file_path']);
    exit();

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['contract_id'])) {
    
    // Get documents for a contract
    $contractId = (int)$_GET['contract_id'];
    
    $stmt = $pdo->prepare("
        SELECT id, document_name, original_filename, file_size, document_type, upload_date, description, mime_type
        FROM kb_contract_documents 
        WHERE contract_id = ? AND is_visible_to_client = 1 
        ORDER BY upload_date DESC
    ");
    $stmt->execute([$contractId]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);

} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>
