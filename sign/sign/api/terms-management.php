<?php
/**
 * Terms Management API
 * Fixed for kb_terms table structure
 * Save as: /sign/api/terms-management.php
 */

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
    $current_user = ['id' => 1, 'role' => 'admin'];
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // Get current active terms from kb_terms table
            $stmt = $db->prepare('SELECT * FROM kb_terms WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1');
            $stmt->execute();
            $currentTerms = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get all terms versions from kb_terms table
            $stmt = $db->prepare('SELECT id, version, upload_date, created_at, is_active, LENGTH(content) as content_length FROM kb_terms ORDER BY created_at DESC');
            $stmt->execute();
            $allVersions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'currentTerms' => $currentTerms,
                'allVersions' => $allVersions,
                'debug' => [
                    'table' => 'kb_terms',
                    'active_column' => 'is_active',
                    'total_versions' => count($allVersions)
                ]
            ]);
            break;
            
        case 'POST':
            // Upload new terms and conditions
            if (isset($_FILES['termsFile'])) {
                $file = $_FILES['termsFile'];
                
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../uploads/terms/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $newFileName = 'terms_' . time() . '.' . $fileExt;
                    $filePath = $uploadDir . $newFileName;
                    
                    if (move_uploaded_file($file['tmp_name'], $filePath)) {
                        
                        // Read file content
                        $content = '';
                        if ($fileExt === 'txt') {
                            $content = file_get_contents($filePath);
                        } else {
                            $content = 'File uploaded: ' . $file['name'] . "\n\nPlease refer to the uploaded file for complete terms and conditions.";
                        }
                        
                        // Get next version number
                        $stmt = $db->prepare('SELECT MAX(CAST(SUBSTRING(version, 1, LOCATE(".", version) - 1) AS UNSIGNED)) as max_version FROM kb_terms');
                        $stmt->execute();
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $next_version = ($result['max_version'] ?? 0) + 1;
                        $version = 'v' . date('Y.m.d') . '.' . $next_version;
                        
                        // Deactivate previous terms (use is_active not active)
                        $stmt = $db->prepare('UPDATE kb_terms SET is_active = 0');
                        $stmt->execute();
                        
                        // Insert new terms into kb_terms table
                        $stmt = $db->prepare('
                            INSERT INTO kb_terms (version, content, is_active, upload_date, created_at) 
                            VALUES (?, ?, 1, CURDATE(), NOW())
                        ');
                        $stmt->execute([$version, $content]);
                        
                        $new_id = $db->lastInsertId();
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Terms and conditions updated successfully',
                            'data' => [
                                'id' => $new_id,
                                'version' => $version,
                                'filepath' => $filePath,
                                'original_filename' => $file['name'],
                                'content_length' => strlen($content)
                            ]
                        ]);
                    } else {
                        throw new Exception('Failed to save terms file');
                    }
                } else {
                    throw new Exception('File upload error: ' . $file['error']);
                }
            } else {
                // Manual terms content update
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($input['content']) || empty($input['content'])) {
                    throw new Exception('Terms content required');
                }
                
                // Get next version number
                $stmt = $db->prepare('SELECT MAX(CAST(SUBSTRING(version, 1, LOCATE(".", version) - 1) AS UNSIGNED)) as max_version FROM kb_terms');
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $next_version = ($result['max_version'] ?? 0) + 1;
                $version = 'v' . date('Y.m.d.H.i') . '.' . $next_version;
                
                // Deactivate previous terms (use is_active not active)
                $stmt = $db->prepare('UPDATE kb_terms SET is_active = 0');
                $stmt->execute();
                
                // Insert new terms into kb_terms table
                $stmt = $db->prepare('
                    INSERT INTO kb_terms (version, content, is_active, upload_date, created_at) 
                    VALUES (?, ?, 1, CURDATE(), NOW())
                ');
                $stmt->execute([$version, $input['content']]);
                
                $new_id = $db->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Terms and conditions updated successfully',
                    'data' => [
                        'id' => $new_id,
                        'version' => $version,
                        'content_length' => strlen($input['content'])
                    ]
                ]);
            }
            break;
            
        case 'PUT':
            // Activate a specific version
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id'])) {
                throw new Exception('Terms ID required');
            }
            
            // Deactivate all terms (use is_active not active)
            $stmt = $db->prepare('UPDATE kb_terms SET is_active = 0');
            $stmt->execute();
            
            // Activate selected version (use is_active not active)
            $stmt = $db->prepare('UPDATE kb_terms SET is_active = 1 WHERE id = ?');
            $result = $stmt->execute([$input['id']]);
            
            if ($result) {
                // Get the activated terms info
                $stmt = $db->prepare('SELECT version, upload_date FROM kb_terms WHERE id = ?');
                $stmt->execute([$input['id']]);
                $termsInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Terms version activated successfully',
                    'data' => [
                        'activated_id' => $input['id'],
                        'version' => $termsInfo['version'] ?? 'Unknown',
                        'upload_date' => $termsInfo['upload_date'] ?? 'Unknown'
                    ]
                ]);
            } else {
                throw new Exception('Failed to activate terms version');
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage(),
        'debug' => [
            'line' => $e->getLine(),
            'file' => basename($e->getFile()),
            'table' => 'kb_terms',
            'expected_columns' => ['id', 'version', 'content', 'is_active', 'upload_date', 'created_at']
        ]
    ]);
}