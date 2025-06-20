<?php
/**
 * Terms and Conditions API Endpoint
 * Fixed for kb_terms table structure
 * Save as: /sign/api/terms.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

require_once '../config/config.php';

// Simple authentication check
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$current_user = null;

if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
    $token = $matches[1];
    // Simple token validation
    if (preg_match('/token-(\d+)-/', $token, $matches)) {
        $current_user = ['id' => $matches[1], 'role' => 'admin'];
    }
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    
    switch ($method) {
        case 'GET':
            // Get current active terms from kb_terms table
            $stmt = $db->prepare('SELECT * FROM kb_terms WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1');
            $stmt->execute();
            $terms = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($terms) {
                echo json_encode([
                    'success' => true,
                    'terms' => [
                        'id' => $terms['id'],
                        'content' => $terms['content'],
                        'version' => $terms['version'],
                        'upload_date' => $terms['upload_date'],
                        'created_at' => $terms['created_at'],
                        'is_active' => $terms['is_active']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'terms' => null
                ]);
            }
            break;
            
        case 'POST':
            // Upload new terms and conditions
            if (!$current_user || $current_user['role'] !== 'admin') {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
            
            // Handle file upload
            if (isset($_FILES['termsFile'])) {
                $file = $_FILES['termsFile'];
                
                // Validate file
                $allowed_types = ['text/plain', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                if (!in_array($file['type'], $allowed_types)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid file type. Please upload TXT, PDF, DOC, or DOCX files.']);
                    exit;
                }
                
                if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 10MB.']);
                    exit;
                }
                
                // Read file content
                $content = '';
                
                if ($file['type'] === 'text/plain') {
                    $content = file_get_contents($file['tmp_name']);
                } elseif ($file['type'] === 'application/pdf') {
                    // For PDF files, we'll store a reference and extract text (simplified)
                    $content = "Terms and Conditions\n\n[PDF file uploaded: " . $file['name'] . "]\n\nPlease refer to the uploaded PDF document for complete terms and conditions.";
                } else {
                    // For DOC/DOCX files, we'll store a reference (in production, you'd use a library to extract text)
                    $content = "Terms and Conditions\n\n[Document uploaded: " . $file['name'] . "]\n\nPlease refer to the uploaded document for complete terms and conditions.";
                }
                
                // Save file to uploads directory
                $upload_dir = '../uploads/terms/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $filename = time() . '_' . basename($file['name']);
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Get next version number
                    $stmt = $db->prepare('SELECT MAX(CAST(SUBSTRING(version, 1, LOCATE(".", version) - 1) AS UNSIGNED)) as max_version FROM kb_terms');
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $next_version = ($result['max_version'] ?? 0) + 1;
                    $version = $next_version . '.0';
                    
                    // Deactivate all previous terms
                    $stmt = $db->prepare('UPDATE kb_terms SET is_active = 0');
                    $stmt->execute();
                    
                    // Insert into kb_terms table with correct structure
                    $stmt = $db->prepare('INSERT INTO kb_terms (version, content, is_active, upload_date, created_at) VALUES (?, ?, 1, CURDATE(), NOW())');
                    $stmt->execute([$version, $content]);
                    
                    $new_id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Terms and conditions uploaded successfully',
                        'data' => [
                            'id' => $new_id,
                            'version' => $version,
                            'content_length' => strlen($content),
                            'filepath' => $filepath
                        ]
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
                }
                
            } else {
                // Handle direct content upload
                $input = json_decode(file_get_contents('php://input'), true);
                $content = $input['content'] ?? '';
                
                if (empty($content)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Content is required']);
                    exit;
                }
                
                // Get next version number
                $stmt = $db->prepare('SELECT MAX(CAST(SUBSTRING(version, 1, LOCATE(".", version) - 1) AS UNSIGNED)) as max_version FROM kb_terms');
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $next_version = ($result['max_version'] ?? 0) + 1;
                $version = $next_version . '.0';
                
                // Deactivate all previous terms
                $stmt = $db->prepare('UPDATE kb_terms SET is_active = 0');
                $stmt->execute();
                
                // Insert into kb_terms table
                $stmt = $db->prepare('INSERT INTO kb_terms (version, content, is_active, upload_date, created_at) VALUES (?, ?, 1, CURDATE(), NOW())');
                $stmt->execute([$version, $content]);
                
                $new_id = $db->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Terms and conditions saved successfully',
                    'data' => [
                        'id' => $new_id,
                        'version' => $version,
                        'content_length' => strlen($content)
                    ]
                ]);
            }
            break;
            
        case 'PUT':
            // Update existing terms or activate a version
            if (!$current_user || $current_user['role'] !== 'admin') {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (isset($input['activate_id'])) {
                // Activate a specific version
                $terms_id = $input['activate_id'];
                
                // Deactivate all terms
                $stmt = $db->prepare('UPDATE kb_terms SET is_active = 0');
                $stmt->execute();
                
                // Activate selected version
                $stmt = $db->prepare('UPDATE kb_terms SET is_active = 1 WHERE id = ?');
                $result = $stmt->execute([$terms_id]);
                
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Terms version activated successfully'
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Failed to activate terms version']);
                }
            } else {
                // Update content of existing terms
                $terms_id = $input['id'] ?? '';
                $content = $input['content'] ?? '';
                
                if (empty($terms_id) || empty($content)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID and content are required']);
                    exit;
                }
                
                $stmt = $db->prepare('UPDATE kb_terms SET content = ? WHERE id = ?');
                $result = $stmt->execute([$content, $terms_id]);
                
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Terms and conditions updated successfully'
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Failed to update terms']);
                }
            }
            break;
            
        case 'DELETE':
            // Delete terms (admin only)
            if (!$current_user || $current_user['role'] !== 'admin') {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $terms_id = $input['id'] ?? '';
            
            if (empty($terms_id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID is required']);
                exit;
            }
            
            $stmt = $db->prepare('DELETE FROM kb_terms WHERE id = ?');
            $result = $stmt->execute([$terms_id]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Terms and conditions deleted successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to delete terms']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Terms API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}